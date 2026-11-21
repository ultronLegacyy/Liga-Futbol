<?php

namespace Tests\Feature;

use App\Models\CatalogoArticulo;
use App\Models\CedulaArbitral;
use App\Models\ExpedienteDisciplinario;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use App\Services\CedulaService;
use App\Services\ControlSancionesDeportivasService;
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
 * Bloqueo automático de participación de jugadores sancionados vigentes (por partidos o tiempo),
 * con mensajes explícitos de la regla deportiva [IU-05] desde el momento del dictamen (RNF-43 / PO #48).
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

    private ControlSancionesDeportivasService $controlSancionesService;

    private ValidacionCedulaService $validacionCedulaService;

    private User $arbitroUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(CatalogoSancionesArticulosSeeder::class);

        $this->partido = $this->partidoConArbitro();
        $this->cedulaService = app(CedulaService::class);
        $this->controlSancionesService = app(ControlSancionesDeportivasService::class);
        $this->validacionCedulaService = app(ValidacionCedulaService::class);

        $this->cedula = $this->cedulaService->abrir($this->partido);
        $this->arbitroUser = $this->usuario('Árbitro en Campo', $this->partido->arbitro);

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
     * HU-047 CA1 y CA3 / IU-05: Intento de alinear vía servicio y vía HTTP PUT en cédula arbitral
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
     * PO #48 / RNF-43: Una sanción recién dictaminada bloquea inmediatamente al jugador aunque falte validación en boletín.
     */
    public function test_bloquea_alineacion_desde_dictamen_sin_esperar_validacion_en_boletin(): void
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
            'partidos_suspendidos' => 1,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => false,
            'validado_por_usuario_id' => null,
        ]);

        $response = $this->actingAs($this->arbitroUser)
            ->putJson(route('cedulas.alineacion', [$this->partido, $jugador->id]), [
                'asistio' => true,
                'numero_camiseta' => 14,
                'clave_envio' => (string) Str::uuid(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['jugador_id']);
        $this->assertStringContainsString('[IU-05]', (string) $response->json('errors.jugador_id.0'));
    }

    /**
     * Regresión de aislamiento: La sanción individual de un jugador no bloquea a otros compañeros de su equipo.
     */
    public function test_sancion_individual_de_jugador_no_bloquea_companeros(): void
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

        $response = $this->actingAs($this->arbitroUser)
            ->putJson(route('cedulas.alineacion', [$this->partido, $companeroElegible->id]), [
                'asistio' => true,
                'numero_camiseta' => 8,
                'clave_envio' => (string) Str::uuid(),
            ]);

        $response->assertOk();
    }

    /**
     * PO #47: Multas económicas y amonestaciones vigentes NO bloquean participación deportiva.
     */
    public function test_multa_economica_y_amonestacion_vigentes_no_bloquean_alineacion(): void
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
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_AMONESTACION,
            'descripcion_sancion' => 'Amonestación formal registrada',
            'partidos_suspendidos' => 0,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        $this->controlSancionesService->validarAlineacionJugador($jugador, $this->partido);

        $this->assertTrue(true);
    }

    /**
     * Regla temporal y mutación >= en fecha_fin: Suspensión temporal bloquea en primer y último día y permite fuera del rango.
     */
    public function test_suspension_temporal_respeta_limites_inclusivos_de_fechas_en_jugador(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_TEMPORAL,
            'descripcion_sancion' => 'Suspensión temporal de 15 días',
            'partidos_suspendidos' => 0,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-12-01',
            'fecha_fin' => '2026-12-15',
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        // Un día antes y un día después deben permitirse
        $this->partido->forceFill(['fecha_partido' => '2026-11-30']);
        $this->controlSancionesService->validarAlineacionJugador($jugador, $this->partido);

        $this->partido->forceFill(['fecha_partido' => '2026-12-16']);
        $this->controlSancionesService->validarAlineacionJugador($jugador, $this->partido);

        // El primer día (2026-12-01) bloquea
        $bloqueadoInicio = false;
        try {
            $this->partido->forceFill(['fecha_partido' => '2026-12-01']);
            $this->controlSancionesService->validarAlineacionJugador($jugador, $this->partido);
        } catch (ValidationException $e) {
            $bloqueadoInicio = str_contains($e->validator->errors()->first('jugador_id'), '[IU-05]');
        }
        $this->assertTrue($bloqueadoInicio, 'El primer día de vigencia debe bloquear al jugador.');

        // El último día (2026-12-15) también debe bloquear (>= inclusivo)
        $this->expectException(ValidationException::class);
        $this->partido->forceFill(['fecha_partido' => '2026-12-15']);
        $this->controlSancionesService->validarAlineacionJugador($jugador, $this->partido);
    }

    /**
     * RF-10.4: Jugador sin sanciones vigentes o con sanción cumplida se alinea exitosamente.
     */
    public function test_permite_alineacion_de_jugador_sin_sancion_o_con_sancion_cumplida(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();

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
     * HU-047 / ValidacionCedulaService: Detecta al cierre de cédula si un jugador alineado tiene sanción vigente con IU-05.
     */
    public function test_validacion_cedula_detecta_sanciones_vigentes_en_alineacion_con_iu05(): void
    {
        $jugadorVisitante = Jugador::where('equipo_id', $this->partido->equipo_visitante_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

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
        $tieneHallazgoIu05 = collect($hallazgos)->contains(
            fn (array $h): bool => $h['tipo'] === ValidacionCedulaService::SUSPENDIDO && str_contains($h['mensaje'], '[IU-05]')
        );
        $this->assertTrue($tieneHallazgoIu05);
    }
}
