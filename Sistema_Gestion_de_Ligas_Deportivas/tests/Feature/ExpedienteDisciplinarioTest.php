<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
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
     * al cerrarse, vinculando personas, equipos y evidencias.
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
        $this->assertMatchesRegularExpression('/^EXP-\d{4}-\d{4}$/', (string) $expediente->folio);
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
    }

    /**
     * HU-044: Cédula con doble tarjeta amarilla para un jugador registra amonestación y expulsión.
     */
    public function test_cierre_de_cedula_con_doble_tarjeta_amarilla_registra_involucrados_y_evidencias(): void
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

        // Verificar que la primera amarilla se registra como amonestado y la segunda como expulsado
        $involucrados = $expediente->involucrados()->where('persona_id', $jugador->persona_id)->get();
        $this->assertCount(2, $involucrados);
        $this->assertSame('jugador_amonestado', $involucrados[0]->tipo_involucrado);
        $this->assertStringContainsString('minuto 25', $involucrados[0]->descripcion_participacion);
        $this->assertSame('jugador_expulsado', $involucrados[1]->tipo_involucrado);
        $this->assertStringContainsString('minuto 65', $involucrados[1]->descripcion_participacion);
    }

    /**
     * HU-044: Cédula con incidencias de porra/campo e informe arbitral registra evidencias e involucrados.
     */
    public function test_cierre_de_cedula_con_incidencias_e_informe_arbitral_registra_evidencias_e_involucrados(): void
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
        $articulo = CatalogoArticulo::where('codigo', 'ART-12')->firstOrFail();

        // Imputar artículo reglamentario
        $this->expedientesService->asociarArticuloPresunto($expediente, $articulo->id, 'Imputación manual por Tribunal');

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
     * Punto 2 Code Review: Árbitro en Campo recibe 403 Forbidden estricto en index y show
     * para evitar la fuga de información disciplinaria y CURP de personas involucradas.
     */
    public function test_arbitro_en_campo_recibe_403_prohibido_en_expedientes_disciplinarios(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 18], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();

        // El árbitro en campo no cuenta con acceso al módulo disciplinario
        $this->actingAs($this->arbitro)
            ->get(route('disciplina.expedientes.index'))
            ->assertForbidden();

        $this->actingAs($this->arbitro)
            ->get(route('disciplina.expedientes.show', $expediente))
            ->assertForbidden();
    }

    /**
     * Punto 1 Code Review: Generación concurrente de expedientes genera folios únicos sin colisión.
     */
    public function test_generacion_concurrente_de_expedientes_garantiza_folios_unicos_sin_colision(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 44], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $expediente1 = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();

        // Crear un segundo partido y cédula con incidencia para simular cierre subsecuente/concurrente
        $partido2 = $this->partidoConArbitro();
        $cedula2 = $this->cedulas->abrir($partido2);
        foreach (['local', 'visitante'] as $lado) {
            $equipoId = $lado === 'local' ? $partido2->equipo_local_id : $partido2->equipo_visitante_id;
            $jugadorCap = Jugador::query()->where('equipo_id', $equipoId)->where('estado_elegibilidad', 'Activo')->firstOrFail();
            $this->cedulas->guardarAlineacion($cedula2, $jugadorCap->id, ['asistio' => true, 'es_capitan' => true], (string) Str::uuid());
        }
        $jugador2 = Jugador::query()->where('equipo_id', $partido2->equipo_local_id)->where('estado_elegibilidad', 'Activo')->firstOrFail();
        $this->cedulas->registrarEvento($cedula2, ['jugador_id' => $jugador2->id, 'tipo' => 'Roja', 'minuto' => 88], (string) Str::uuid());
        $this->cedulas->guardarMarcador($cedula2, 0, 0, (string) Str::uuid());

        $arbitro2 = $this->usuario('Árbitro en Campo', $partido2->arbitro);
        foreach (['local', 'visitante'] as $lado) {
            $this->actingAs($arbitro2)
                ->post(route('cedulas.firmar', [$partido2, $lado]), ['firma' => $this->firma()])
                ->assertSessionHasNoErrors();
        }

        $this->cierre->cerrar($cedula2, $arbitro2);

        $expediente2 = ExpedienteDisciplinario::where('cedula_id', $cedula2->id)->firstOrFail();

        $this->assertNotSame($expediente1->id, $expediente2->id);
        $this->assertNotSame($expediente1->folio, $expediente2->folio);
        $this->assertMatchesRegularExpression('/^EXP-\d{4}-\d{4}$/', (string) $expediente1->folio);
        $this->assertMatchesRegularExpression('/^EXP-\d{4}-\d{4}$/', (string) $expediente2->folio);
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
     * Punto 4 Code Review: Auditoría RNF-30 en relación con artículos, involucrados y evidencias.
     * Verifica que altas y bajas generen registros en auditoria_logs.
     */
    public function test_auditoria_logs_registra_imputacion_y_desestimacion_de_articulos_presuntos(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 30], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();
        $articulo = CatalogoArticulo::where('codigo', 'ART-12')->firstOrFail();

        // 1. Verificar que al crear el expediente se auditaron involucrados y evidencias
        $auditoriasInvolucrado = AuditoriaLog::where('tabla_afectada', 'expediente_involucrados')->count();
        $this->assertGreaterThan(0, $auditoriasInvolucrado);

        $auditoriasEvidencia = AuditoriaLog::where('tabla_afectada', 'expediente_evidencias')->count();
        $this->assertGreaterThan(0, $auditoriasEvidencia);

        // 2. Imputar artículo presunto como Tribunal de Penas
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.articulos.store', $expediente), [
                'articulo_id' => $articulo->id,
                'observaciones' => 'Auditoría de imputación reglamentaria',
            ])
            ->assertRedirect();

        $auditoriaCrearArticulo = AuditoriaLog::where('tabla_afectada', 'expediente_articulos')
            ->where('accion', 'crear')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditoriaCrearArticulo);
        $this->assertSame($this->tribunalUser->id, $auditoriaCrearArticulo->usuario_id);
        $this->assertStringContainsString('Imputación', (string) $auditoriaCrearArticulo->motivo);

        // 3. Desestimar artículo presunto
        $this->actingAs($this->tribunalUser)
            ->delete(route('disciplina.expedientes.articulos.destroy', [$expediente, $articulo]))
            ->assertRedirect();

        $auditoriaEliminarArticulo = AuditoriaLog::where('tabla_afectada', 'expediente_articulos')
            ->where('accion', 'eliminar')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditoriaEliminarArticulo);
        $this->assertSame($this->tribunalUser->id, $auditoriaEliminarArticulo->usuario_id);
        $this->assertStringContainsString('Desestimación', (string) $auditoriaEliminarArticulo->motivo);
    }

    /**
     * Punto 5 Code Review: Validación de entradas evita errores 500 y emite mensajes en español.
     */
    public function test_validacion_de_entradas_en_listado_y_asociacion_de_articulos(): void
    {
        $this->capitanes();
        $this->cedulas->registrarEvento($this->cedula, ['jugador_id' => $this->activo('local')->id, 'tipo' => 'Roja', 'minuto' => 15], (string) Str::uuid());
        $this->cedulas->guardarMarcador($this->cedula, 0, 0, (string) Str::uuid());
        $this->firmarAmbos();
        $this->cierre->cerrar($this->cedula, $this->arbitro);

        $expediente = ExpedienteDisciplinario::where('cedula_id', $this->cedula->id)->firstOrFail();
        $articulo = CatalogoArticulo::where('codigo', 'ART-12')->firstOrFail();

        // 1. GET /disciplina/expedientes?buscar[]=x no debe causar HTTP 500 (Array to string conversion)
        $this->actingAs($this->tribunalUser)
            ->get('/disciplina/expedientes?buscar[]=injection')
            ->assertSessionHasErrors(['buscar']);

        // 2. GET /disciplina/expedientes con estado inválido
        $this->actingAs($this->tribunalUser)
            ->get('/disciplina/expedientes?estado=EstadoInexistente')
            ->assertSessionHasErrors(['estado']);

        // 3. POST /disciplina/expedientes/{id}/articulos con observaciones > 255 no causa 500, sino validación 422/302 con mensaje en español
        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.expedientes.articulos.store', $expediente), [
                'articulo_id' => $articulo->id,
                'observaciones' => str_repeat('A', 256),
            ])
            ->assertSessionHasErrors([
                'observaciones' => 'Las observaciones no deben exceder de 255 caracteres.',
            ]);

        // 4. POST y DELETE con usuario no autorizado responde 403 Forbidden
        $this->actingAs($this->delegadoUser)
            ->post(route('disciplina.expedientes.articulos.store', $expediente), [
                'articulo_id' => $articulo->id,
            ])
            ->assertForbidden();

        $this->actingAs($this->delegadoUser)
            ->delete(route('disciplina.expedientes.articulos.destroy', [$expediente, $articulo]))
            ->assertForbidden();
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
