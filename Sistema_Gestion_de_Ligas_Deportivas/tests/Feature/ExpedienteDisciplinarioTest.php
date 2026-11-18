<?php

namespace Tests\Feature;

use App\Models\CatalogoArticulo;
use App\Models\CedulaArbitral;
use App\Models\ExpedienteDisciplinario;
use App\Models\ExpedienteEvidencia;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use App\Services\CedulaService;
use App\Services\CierreCedulaService;
use App\Services\ExpedienteDisciplinarioService;
use Database\Seeders\CatalogoSancionesArticulosSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * Pruebas integrales para HU-044 / RF-10.1:
 * Generación automática de expediente disciplinario a partir de las incidencias de la cédula,
 * personas, equipos, evidencias y artículos del catálogo presuntamente infringidos (REF-05).
 */
class ExpedienteDisciplinarioTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private CedulaService $cedulas;

    private CierreCedulaService $cierre;

    private ExpedienteDisciplinarioService $expedientesService;

    private Partido $partido;

    private CedulaArbitral $cedula;

    private User $arbitro;

    private User $tribunalUser;

    private User $adminUser;

    private User $delegadoUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(CatalogoSancionesArticulosSeeder::class);

        $this->cedulas = app(CedulaService::class);
        $this->cierre = app(CierreCedulaService::class);
        $this->expedientesService = app(ExpedienteDisciplinarioService::class);

        $this->partido = $this->partidoConArbitro();
        $this->cedula = $this->cedulas->abrir($this->partido);
        $this->arbitro = $this->usuario('Árbitro en Campo', $this->partido->arbitro);

        $this->tribunalUser = $this->usuario('Tribunal de Penas');
        $this->adminUser = $this->usuario('Administrador General');
        $this->delegadoUser = $this->usuario('Delegado del Club');
    }

    /**
     * HU-044 Criterio 1: Cédula con tarjeta roja genera automáticamente expediente disciplinario
     * al cerrarse, vinculando personas, equipos, evidencias y el artículo presuntamente infringido.
     */
    public function test_cierre_de_cedula_con_tarjeta_roja_genera_expediente_disciplinario_automaticamente(): void
    {
        $this->capitanes();
        $jugadorLocal = $this->activo('local');

        // Registrar tarjeta roja (expulsión)
        $this->cedulas->registrarEvento(
            $this->cedula,
            ['jugador_id' => $jugadorLocal->id, 'tipo' => 'Roja', 'minuto' => 72],
            (string) Str::uuid()
        );
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();

        // El árbitro cierra la cédula
        $this->actingAs($this->arbitro)
            ->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertRedirect(route('cedulas.show', $this->partido))
            ->assertSessionHas('success');

        $this->cedula->refresh();
        $this->assertTrue($this->cedula->estaCerrada());

        // Verificar existencia y consistencia del expediente disciplinario generado
        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->first();
        $this->assertNotNull($expediente);
        $this->assertStringStartsWith('EXP-', (string) $expediente->folio);
        $this->assertSame(ExpedienteDisciplinario::ESTADO_ABIERTO, $expediente->estado);
        $this->assertTrue($expediente->generado_automaticamente);
        $this->assertSame($this->partido->id, $expediente->partido_id);

        // Verificar persona involucrada
        $involucrado = $expediente->involucrados()->where('persona_id', $jugadorLocal->persona_id)->first();
        $this->assertNotNull($involucrado);
        $this->assertSame('jugador_expulsado', $involucrado->tipo_involucrado);
        $this->assertSame($jugadorLocal->equipo_id, $involucrado->equipo_id);

        // Verificar equipos involucrados
        $equiposInvolucrados = $expediente->involucrados()->whereNotNull('equipo_id')->pluck('equipo_id')->all();
        $this->assertContains($this->partido->equipo_local_id, $equiposInvolucrados);
        $this->assertContains($this->partido->equipo_visitante_id, $equiposInvolucrados);

        // Verificar evidencias asociadas (tarjeta roja con minuto)
        $evidencia = $expediente->evidencias()->where('tipo_evidencia', ExpedienteEvidencia::TIPO_TARJETA)->first();
        $this->assertNotNull($evidencia);
        $this->assertSame(72, $evidencia->minuto);
        $this->assertStringContainsString('Tarjeta Roja directa', $evidencia->descripcion);

        // HU-044 Criterio 2: Muestra artículos presuntamente infringidos (ART-12)
        $articulosPresuntos = $expediente->articulos->pluck('codigo')->all();
        $this->assertContains('ART-12', $articulosPresuntos);
        $this->assertSame('ART-12', $expediente->articulo?->codigo);
    }

    /**
     * HU-044: Cédula con doble tarjeta amarilla para un jugador imputa ART-14 (Expulsión por doble amonestación).
     */
    public function test_cierre_de_cedula_con_doble_tarjeta_amarilla_imputa_articulo_de_doble_amonestacion(): void
    {
        $this->capitanes();
        $jugador = $this->activo('local');

        // Primera amarilla minuto 25, segunda amarilla minuto 65
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $jugador->id, 'tipo' => 'Amarilla', 'minuto' => 25], (string) Str::uuid());
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $jugador->id, 'tipo' => 'Amarilla', 'minuto' => 65], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();

        $this->actingAs($this->arbitro)
            ->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertRedirect();

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();
        $articulosCodigos = $expediente->articulos->pluck('codigo')->all();

        $this->assertContains('ART-08', $articulosCodigos);
        $this->assertContains('ART-14', $articulosCodigos);

        // Verificar que la primera amarilla se registra como amonestado y la segunda como expulsado
        $involucrados = $expediente->involucrados()->where('persona_id', $jugador->persona_id)->get();
        $this->assertCount(2, $involucrados);
        $this->assertSame('jugador_amonestado', $involucrados[0]->tipo_involucrado);
        $this->assertStringContainsString('minuto 25', $involucrados[0]->descripcion_participacion);
        $this->assertSame('jugador_expulsado', $involucrados[1]->tipo_involucrado);
        $this->assertStringContainsString('minuto 65', $involucrados[1]->descripcion_participacion);
    }

    /**
     * HU-044: Cédula con incidencias de porra/campo e informe arbitral asocia ART-15, ART-30 y evidencias.
     */
    public function test_cierre_de_cedula_con_incidencias_e_informe_arbitral_asocia_articulos_correspondientes(): void
    {
        $this->capitanes();
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());

        // Incidencia reportada
        $this->cedulas->registrarIncidencia(
            $this->cedula,
            [
                'equipo_id' => $this->partido->equipo_local_id,
                'minuto' => 85,
                'descripcion' => 'Invasión de cancha y riña provocada por simpatizantes y porra del equipo',
            ],
            (string) Str::uuid()
        );

        // Informe arbitral
        $this->cedulas->guardarInforme(
            $this->cedula,
            'Al minuto 85 el partido fue suspendido provisionalmente por insultos y agresión verbal al cuerpo arbitral',
            (string) Str::uuid()
        );

        $this->firmarAmbos();

        $this->actingAs($this->arbitro)
            ->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();
        $codigos = $expediente->articulos->pluck('codigo')->all();

        // Detecta ART-30 (riña/porra) y ART-15 (insultos)
        $this->assertContains('ART-30', $codigos);
        $this->assertContains('ART-15', $codigos);

        // Evidencias de informe e incidencias
        $tiposEvidencias = $expediente->evidencias->pluck('tipo_evidencia')->all();
        $this->assertContains(ExpedienteEvidencia::TIPO_INCIDENCIA, $tiposEvidencias);
        $this->assertContains(ExpedienteEvidencia::TIPO_INFORME, $tiposEvidencias);
    }

    /**
     * HU-044: Una cédula limpia sin incidencias ni tarjetas no debe generar expediente disciplinario.
     */
    public function test_cierre_de_cedula_limpia_sin_incidencias_no_genera_expediente(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Gol', 'minuto' => 10], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 1, 0, (string) Str::uuid());
        $this->firmarAmbos();

        $this->actingAs($this->arbitro)
            ->post(route('cedulas.cerrar', $this->partido), ['confirmar' => '1'])
            ->assertRedirect();

        $this->cedula->refresh();
        $this->assertTrue($this->cedula->estaCerrada());
        $this->assertSame(0, ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->count());
    }

    /**
     * Idempotencia: invocar nuevamente la generación no duplica registros.
     */
    public function test_idempotencia_no_duplica_expediente_si_ya_existe(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 50], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();

        $this->cierre->cerrar($this->cedula, $this->arbitro);
        $conteoInicial = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->count();
        $this->assertSame(1, $conteoInicial);

        // Segundo intento de generación
        $resultado = $this->expedientesService->generarDesdeCedulaSiAplica($this->cedula);
        $this->assertNotNull($resultado);
        $this->assertSame(1, ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->count());
    }

    /**
     * HU-044 Criterio 2: Dado un expediente generado, cuando se revisa en la interfaz,
     * muestra los artículos del catálogo reglamentario presuntamente infringidos (REF-05).
     */
    public function test_consulta_de_expediente_muestra_articulos_del_catalogo_criterio_2(): void
    {
        $this->capitanes();
        $jugador = $this->activo('local');
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $jugador->id, 'tipo' => 'Roja', 'minuto' => 80], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();

        // Autenticado como Tribunal de Penas consulta la vista detallada
        $this->actingAs($this->tribunalUser)
            ->get(route('disciplina.expedientes.show', $expediente))
            ->assertOk()
            ->assertSee($expediente->folio)
            ->assertSee('ART-12')
            ->assertSee('Juego brusco grave o conducta violenta')
            ->assertSee('Castigo base de referencia: 2 partidos')
            ->assertSee($jugador->persona->nombre)
            ->assertSee('Tarjeta Roja directa');
    }

    /**
     * Control de accesos por roles: Tribunal de Penas y Administrador General pueden acceder;
     * roles no autorizados reciben 403 Forbidden.
     */
    public function test_control_de_acceso_y_permisos_por_rol(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 12], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();

        // 1. Tribunal de Penas: Permitido
        $this->actingAs($this->tribunalUser)
            ->get(route('disciplina.expedientes.index'))
            ->assertOk();

        $this->actingAs($this->tribunalUser)
            ->get(route('disciplina.expedientes.show', $expediente))
            ->assertOk();

        // 2. Administrador General: Permitido
        $this->actingAs($this->adminUser)
            ->get(route('disciplina.expedientes.index'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('disciplina.expedientes.show', $expediente))
            ->assertOk();

        // 3. Delegado del Club: Denegado 403
        $this->actingAs($this->delegadoUser)
            ->get(route('disciplina.expedientes.index'))
            ->assertForbidden();

        $this->actingAs($this->delegadoUser)
            ->get(route('disciplina.expedientes.show', $expediente))
            ->assertForbidden();
    }

    /**
     * El Tribunal de Penas puede imputar un artículo presunto adicional y desestimarlo.
     */
    public function test_tribunal_de_penas_puede_imputar_y_desestimar_articulos_presuntos(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 30], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();
        $articuloInsultos = CatalogoArticulo::where('codigo', 'ART-15')->firstOrFail();

        // Imputar artículo adicional
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.articulos.store', $expediente), [
                'articulo_id' => $articuloInsultos->id,
                'observaciones' => 'Insultos agregados en reporte complementario',
            ])
            ->assertRedirect(route('disciplina.expedientes.show', $expediente))
            ->assertSessionHas('success');

        $this->assertTrue($expediente->refresh()->articulos->contains('id', $articuloInsultos->id));

        // Desestimar el artículo imputado
        $this->actingAs($this->tribunalUser)
            ->delete(route('disciplina.expedientes.articulos.destroy', [$expediente, $articuloInsultos]))
            ->assertRedirect(route('disciplina.expedientes.show', $expediente))
            ->assertSessionHas('success');

        $this->assertFalse($expediente->refresh()->articulos->contains('id', $articuloInsultos->id));
    }

    /**
     * Regla de negocio: Un expediente no puede quedar sin artículos reglamentarios presuntos.
     */
    public function test_no_se_puede_desestimar_el_unico_articulo_del_expediente(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 30], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();
        $unicoArticulo = $expediente->articulos()->firstOrFail();

        $this->actingAs($this->tribunalUser)
            ->delete(route('disciplina.expedientes.articulos.destroy', [$expediente, $unicoArticulo]))
            ->assertSessionHasErrors('articulo');

        $this->assertSame(1, $expediente->refresh()->articulos()->count());
    }

    /**
     * HU-031 / HU-044: Un partido con expedientes disciplinarios no puede eliminarse (blindaje deportivo).
     */
    public function test_partido_con_expediente_disciplinario_no_se_puede_eliminar(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 15], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $this->assertFalse($this->partido->esEliminable());
    }

    // --- Métodos auxiliares de prueba ---

    private function capitanes(): void
    {
        foreach (['local', 'visitante'] as $lado) {
            $this->cedulas->guardarAlineacion(
                $this->cedula,
                $this->activo($lado)->id,
                ['asistio' => true, 'es_capitan' => true],
                (string) Str::uuid()
            );
        }
    }

    private function firmarAmbos(): void
    {
        foreach (['local', 'visitante'] as $lado) {
            $this->actingAs($this->arbitro)
                ->post(route('cedulas.firmar', [$this->partido, $lado]), ['firma' => $this->firma()])
                ->assertSessionHasNoErrors();
        }
    }

    private function activo(string $lado): Jugador
    {
        $equipoId = $lado === 'local' ? $this->partido->equipo_local_id : $this->partido->equipo_visitante_id;

        return Jugador::query()
            ->where('equipo_id', $equipoId)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();
    }

    private function firma(): string
    {
        // 100x50 PNG base64 válido
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAAAyCAYAAACqNX6+AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAiSURBVHhe7cExAQAAAMKg9U9tCy8gAAAAAAAAAAAAAAAAvg0V5AABkOQ8TwAAAABJRU5ErkJggg==';
    }
}
