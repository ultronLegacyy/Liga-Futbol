<?php

namespace Tests\Feature;

use App\Models\Cancha;
use App\Models\CatalogoArticulo;
use App\Models\CedulaArbitral;
use App\Models\Equipo;
use App\Models\ExpedienteDisciplinario;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use App\Services\CedulaService;
use App\Services\ControlSancionesDeportivasService;
use App\Services\ProgramacionJornadaService;
use App\Services\PublicacionProgramacionService;
use App\Services\ValidacionCedulaService;
use Database\Seeders\CatalogoSancionesArticulosSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * Pruebas de integración para SCRUM-59 / HU-047 / RF-10.4, IU-05:
 * Bloqueo automático de alineación de jugadores sancionados y de programación
 * de equipos suspendidos, con mensajes explícitos de la regla deportiva e inhabilitación.
 */
class BloqueoSancionDeportivaTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private CedulaArbitral $cedula;

    private ExpedienteDisciplinario $expediente;

    private CatalogoArticulo $articulo;

    private CedulaService $cedulaService;

    private ProgramacionJornadaService $programacionService;

    private PublicacionProgramacionService $publicacionService;

    private ControlSancionesDeportivasService $controlSancionesService;

    private ValidacionCedulaService $validacionCedulaService;

    private User $arbitroUser;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(CatalogoSancionesArticulosSeeder::class);

        $this->partido = $this->partidoConArbitro();
        $this->cedulaService = app(CedulaService::class);
        $this->programacionService = app(ProgramacionJornadaService::class);
        $this->publicacionService = app(PublicacionProgramacionService::class);
        $this->controlSancionesService = app(ControlSancionesDeportivasService::class);
        $this->validacionCedulaService = app(ValidacionCedulaService::class);

        $this->cedula = $this->cedulaService->abrir($this->partido);
        $this->arbitroUser = $this->usuario('Árbitro en Campo', $this->partido->arbitro);
        $this->adminUser = $this->usuario('Administrador General');

        $this->articulo = CatalogoArticulo::firstOrCreate(
            ['codigo' => 'ART-303'],
            [
                'descripcion' => 'Conducta antideportiva y desacato al Tribunal',
                'castigo_base_partidos' => 3,
                'es_grave' => true,
            ]
        );

        $this->expediente = ExpedienteDisciplinario::create([
            'folio' => 'EXP-2026-BLQ-01',
            'partido_id' => $this->partido->id,
            'articulo_id' => $this->articulo->id,
            'titulo' => 'Expediente de prueba de bloqueos',
            'descripcion_hechos' => 'Incidencia disciplinaria para verificar bloqueos IU-05',
            'origen' => 'cedula_arbitral',
            'estado' => ExpedienteDisciplinario::ESTADO_RESUELTO,
            'fecha_apertura' => now(),
            'generado_automaticamente' => true,
        ]);
    }

    /**
     * RF-10.4 / IU-05 Criterio 1: Intento de alinear vía servicio y vía HTTP PUT en cédula arbitral
     * a un jugador con sanción vigente es rechazado con HTTP 422 y mensaje IU-05 con folio, artículo y castigo.
     */
    public function test_bloquea_alineacion_de_jugador_con_sancion_vigente_con_mensaje_iu05(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión por 3 partidos oficiales',
            'motivo_resolucion' => 'Sanción firme dictaminada',
            'partidos_suspendidos' => 3,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
        ]);

        // 1. Prueba directa del servicio de control
        try {
            $this->controlSancionesService->validarAlineacionJugador($jugador, $this->partido);
            $this->fail('Se esperaba ValidationException por sanción vigente.');
        } catch (ValidationException $e) {
            $error = $e->validator->errors()->first('jugador_id');
            $this->assertStringContainsString('[IU-05]', $error);
            $this->assertStringContainsString('inhabilitado por sanción deportiva vigente', $error);
            $this->assertStringContainsString($this->expediente->folio, $error);
            $this->assertStringContainsString($this->articulo->codigo, $error);
            $this->assertStringContainsString('3 partido(s) de suspensión', $error);
        }

        // 2. Prueba HTTP real PUT en cédula arbitral (422 JSON con mensaje IU-05)
        $response = $this->actingAs($this->arbitroUser)
            ->putJson(route('cedulas.alineacion', [$this->partido, $jugador->id]), [
                'asistio' => true,
                'numero_camiseta' => 10,
                'clave_envio' => (string) Str::uuid(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['jugador_id']);
        $mensajeJson = (string) $response->json('errors.jugador_id.0');
        $this->assertStringContainsString('[IU-05]', $mensajeJson);
        $this->assertStringContainsString($this->expediente->folio, $mensajeJson);
    }

    /**
     * Regresión de aislamiento: La sanción individual de un jugador (con equipo_id referencial)
     * NO debe bloquear ni a otros compañeros de su equipo ni la programación del club.
     */
    public function test_sancion_individual_de_jugador_no_bloquea_companeros_ni_equipo(): void
    {
        $activos = Jugador::where('equipo_id', $this->partido->equipo_local_id)
            ->where('estado_elegibilidad', 'Activo')
            ->orderBy('id')
            ->get();

        $jugadorSancionado = $activos[0];
        $companeroElegible = $activos[1];

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugadorSancionado->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión individual del jugador',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
        ]);

        // El compañero SÍ puede alinearse vía HTTP
        $response = $this->actingAs($this->arbitroUser)
            ->putJson(route('cedulas.alineacion', [$this->partido, $companeroElegible->id]), [
                'asistio' => true,
                'numero_camiseta' => 8,
                'clave_envio' => (string) Str::uuid(),
            ]);

        $response->assertOk();

        // El equipo NO está suspendido
        $this->controlSancionesService->validarProgramacionEquipo(
            $this->partido->equipo_local_id,
            'equipo_local_id',
            '2026-11-20'
        );
        $this->assertTrue(true);
    }

    /**
     * Regla de negocio: Multas económicas y amonestaciones vigentes NO bloquean participación ni programación.
     */
    public function test_multa_economica_y_amonestacion_vigentes_no_bloquean_alineacion_ni_programacion(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_MULTA,
            'descripcion_sancion' => 'Multa económica por conducta impropia',
            'partidos_suspendidos' => 0,
            'partidos_cumplidos' => 0,
            'monto_multa' => 1500.00,
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => null,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_AMONESTACION,
            'descripcion_sancion' => 'Amonestación institucional al club',
            'partidos_suspendidos' => 0,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        $this->controlSancionesService->validarAlineacionJugador($jugador, $this->partido);
        $this->controlSancionesService->validarProgramacionEquipo($this->partido->equipo_local_id, 'equipo_local_id');

        $this->assertTrue(true);
    }

    /**
     * Regla temporal: Suspensión temporal bloquea dentro del rango [fecha_inicio, fecha_fin] y permite fuera de él.
     */
    public function test_suspension_temporal_respeta_ventana_de_fechas_en_programacion(): void
    {
        $equipo = Equipo::findOrFail($this->partido->equipo_local_id);

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => null,
            'equipo_id' => $equipo->id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_TEMPORAL,
            'descripcion_sancion' => 'Suspensión temporal de 15 días',
            'partidos_suspendidos' => 0,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-12-01',
            'fecha_fin' => '2026-12-15',
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        // Fuera del rango (posterior al 15 de diciembre) debe permitirse
        $this->controlSancionesService->validarProgramacionEquipo($equipo, 'equipo_local_id', '2026-12-20');

        // Dentro del rango (10 de diciembre) debe bloquear con IU-05
        $this->expectException(ValidationException::class);
        $this->controlSancionesService->validarProgramacionEquipo($equipo, 'equipo_local_id', '2026-12-10');
    }

    /**
     * RF-10.4: Jugador sin sanciones vigentes o con sanción cumplida se alinea exitosamente.
     */
    public function test_permite_alineacion_de_jugador_sin_sancion_o_con_sancion_cumplida(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();

        // Crear sanción ya cumplida
        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Sanción anterior cumplida',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 2,
            'estado' => Sancion::ESTADO_CUMPLIDA,
            'publicada_en_boletin' => false,
        ]);

        $this->controlSancionesService->validarAlineacionJugador($jugador, $this->partido);

        $this->assertTrue(true);
    }

    /**
     * RF-10.4 / IU-05 Criterio 2: Intento de programar un partido vía servicio y HTTP POST con equipo suspendido
     * es rechazado con mensaje IU-05 indicando la regla deportiva.
     */
    public function test_bloquea_programacion_de_partido_con_equipo_suspendido_con_mensaje_iu05(): void
    {
        $equipoLocal = Equipo::findOrFail($this->partido->equipo_local_id);
        $equipoVisitante = Equipo::findOrFail($this->partido->equipo_visitante_id);

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => null,
            'equipo_id' => $equipoLocal->id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_EQUIPO,
            'descripcion_sancion' => 'Suspensión del club por incidentes graves',
            'motivo_resolucion' => 'Resolución que inhabilita al equipo para programación',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
        ]);

        try {
            $this->controlSancionesService->validarProgramacionEquipo($equipoLocal, 'equipo_local_id');
            $this->fail('Se esperaba ValidationException por equipo suspendido.');
        } catch (ValidationException $e) {
            $error = $e->validator->errors()->first('equipo_local_id');
            $this->assertStringContainsString('[IU-05]', $error);
            $this->assertStringContainsString('Equipo inhabilitado para programación', $error);
            $this->assertStringContainsString($equipoLocal->nombre, $error);
            $this->assertStringContainsString($this->expediente->folio, $error);
        }

        // Intento HTTP POST de alta manual de partido con equipo sancionado
        $cancha = Cancha::factory()->create();
        $response = $this->actingAs($this->adminUser)
            ->post(route('jornadas.partidos.store', $this->partido->jornada), [
                'equipo_local_id' => $equipoLocal->id,
                'equipo_visitante_id' => $equipoVisitante->id,
                'cancha_id' => $cancha->id,
                'arbitro_id' => $this->partido->arbitro_id,
                'fecha_partido' => $this->partido->jornada->fecha_inicio->toDateString(),
                'hora_partido' => '12:00',
            ]);

        $response->assertSessionHasErrors('equipo_local_id');
    }

    /**
     * RF-10.4 / IU-05: El servicio de programación de jornadas lanza excepción al intentar programar equipo sancionado.
     */
    public function test_programacion_jornada_service_rechaza_equipo_suspendido(): void
    {
        $equipoLocal = Equipo::findOrFail($this->partido->equipo_local_id);
        $equipoVisitante = Equipo::findOrFail($this->partido->equipo_visitante_id);

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => null,
            'equipo_id' => $equipoVisitante->id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_EQUIPO,
            'descripcion_sancion' => 'Suspensión del club visitante',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
        ]);

        $cancha = Cancha::factory()->create();

        $this->expectException(ValidationException::class);

        $this->programacionService->programarPartido($this->partido->jornada, [
            'equipo_local_id' => $equipoLocal->id,
            'equipo_visitante_id' => $equipoVisitante->id,
            'cancha_id' => $cancha->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '12:00:00',
        ], $this->adminUser);
    }

    /**
     * RF-10.4: La detección de conflictos en publicación de jornada y validación de cédula identifican sanciones vigentes.
     */
    public function test_publicacion_y_validacion_cedula_detectan_sanciones_vigentes(): void
    {
        $equipoLocal = Equipo::findOrFail($this->partido->equipo_local_id);
        $jugadorVisitante = Jugador::where('equipo_id', $this->partido->equipo_visitante_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => null,
            'equipo_id' => $equipoLocal->id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_EQUIPO,
            'descripcion_sancion' => 'Suspensión vigente de equipo',
            'partidos_suspendidos' => 1,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
        ]);

        // 1. Conflicto en publicación de jornada
        $conflictos = $this->publicacionService->detectarConflictos($this->partido->jornada);
        $this->assertNotEmpty($conflictos);
        $tieneConflictoSancion = collect($conflictos)->contains(function ($c): bool {
            return str_contains((string) $c, 'sanción disciplinaria vigente');
        });
        $this->assertTrue($tieneConflictoSancion);

        // 2. Validación de cédula si alguien forzó asistencia con sanción vigente en tabla sanciones
        $fila = $this->cedula->alineaciones()->where('jugador_id', $jugadorVisitante->id)->firstOrFail();
        $fila->update(['asistio' => true]);

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugadorVisitante->persona_id,
            'equipo_id' => $this->partido->equipo_visitante_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión posterior del jugador',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        $hallazgos = $this->validacionCedulaService->validar($this->cedula->fresh());
        $tieneHallazgoIu05 = collect($hallazgos)->contains(fn (array $h): bool => $h['tipo'] === ValidacionCedulaService::SUSPENDIDO && str_contains($h['mensaje'], '[IU-05]'));
        $this->assertTrue($tieneHallazgoIu05);
    }
}
