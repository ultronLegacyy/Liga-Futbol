<?php

namespace Tests\Feature;

use App\Models\CatalogoArticulo;
use App\Models\ExpedienteDisciplinario;
use App\Models\ExpedienteInvolucrado;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use App\Services\CedulaService;
use App\Services\CierreCedulaService;
use App\Services\DisciplinaResolucionService;
use App\Services\VigenciaSancionService;
use Database\Seeders\CatalogoSancionesArticulosSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * Pruebas de integración para SCRUM-58 / HU-046 / RF-10.3, RNF-44:
 * Cálculo de vigencia de sanciones, descuento automático de partidos tras cierre real de cédula,
 * zona horaria oficial, visibilidad por rol del boletín e inalterabilidad de RNF-44.
 */
class VigenciaSancionYBoletinTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private Partido $partidoSiguiente;

    private ExpedienteDisciplinario $expediente;

    private User $tribunalUser;

    private User $adminUser;

    private User $delegadoUser;

    private CatalogoArticulo $articulo;

    private VigenciaSancionService $vigenciaService;

    private CedulaService $cedulaService;

    private CierreCedulaService $cierreCedulaService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(CatalogoSancionesArticulosSeeder::class);

        $this->partido = $this->partidoConArbitro();
        $this->tribunalUser = $this->usuario('Tribunal de Penas');
        $this->adminUser = $this->usuario('Administrador General');
        $this->delegadoUser = $this->usuario('Delegado del Club');
        $this->delegadoUser->forceFill(['club_id' => $this->partido->local->club_id])->save();

        $this->vigenciaService = app(VigenciaSancionService::class);
        $this->cedulaService = app(CedulaService::class);
        $this->cierreCedulaService = app(CierreCedulaService::class);

        $jornada2 = Jornada::create([
            'grupo_id' => $this->partido->jornada->grupo_id,
            'numero_jornada' => 2,
            'fecha_inicio' => '2026-11-21',
            'fecha_fin' => '2026-11-22',
        ]);

        $this->partidoSiguiente = Partido::create([
            'jornada_id' => $jornada2->id,
            'equipo_local_id' => $this->partido->equipo_local_id,
            'equipo_visitante_id' => $this->partido->equipo_visitante_id,
            'arbitro_id' => $this->partido->arbitro_id,
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_CONFIRMADO,
        ]);

        $this->articulo = CatalogoArticulo::firstOrCreate(
            ['codigo' => 'ART-202'],
            [
                'descripcion' => 'Insultos reiterados al cuerpo arbitral',
                'castigo_base_partidos' => 2,
                'es_grave' => false,
            ]
        );

        $this->expediente = ExpedienteDisciplinario::create([
            'folio' => 'EXP-2026-VIG-01',
            'partido_id' => $this->partido->id,
            'articulo_id' => $this->articulo->id,
            'titulo' => 'Expediente de prueba de vigencia',
            'descripcion_hechos' => 'Hechos reportados para prueba de vigencia',
            'origen' => 'cedula_arbitral',
            'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO,
            'fecha_apertura' => now(),
            'generado_automaticamente' => true,
        ]);
    }

    /**
     * HU-046 CA1 / PO #47: Suspensión por partidos NO inventa fecha_fin estimada ni días por omisión.
     */
    public function test_calculo_vigencia_inicial_no_inventa_fecha_fin_en_partidos_ni_plazo_30_dias(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

        $sancion = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión de 2 partidos',
            'motivo_resolucion' => 'Aplicación de dos partidos de suspensión conforme a reglamento',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-11-14',
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => false,
        ]);

        $this->vigenciaService->calcularVigencia($sancion);

        $this->assertTrue($sancion->estaVigente());
        $this->assertFalse($sancion->estaCumplida());
        $this->assertEquals(2, $sancion->partidosRestantes());
        $this->assertNull($sancion->partido_suspension_hasta_id);
        $this->assertNull($sancion->fecha_fin);
    }

    /**
     * HU-046 CA2 / RF-10.3: El cierre real de cédula descuenta progresivamente (1 < 2 sigue vigente; 2 == 2 cumple).
     */
    public function test_cierre_real_de_cedula_computa_y_descuenta_partido_jugado_progresivamente(): void
    {
        $activosLocal = Jugador::where('equipo_id', $this->partido->equipo_local_id)
            ->where('estado_elegibilidad', 'Activo')
            ->orderBy('id')
            ->get();
        $jugadorSancionado = $activosLocal[0];
        $capitanLocal = $activosLocal[1];
        $capitanVisitante = Jugador::where('equipo_id', $this->partido->equipo_visitante_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

        $sancion = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugadorSancionado->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión de 2 partidos',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-11-14',
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'autorizado_por_cargo' => 'Tribunal de Penas',
            'fecha_publicacion' => now(),
        ]);

        // Partido origen no se descuenta
        $this->vigenciaService->computarPartidoJugado($this->partido);
        $sancion->refresh();
        $this->assertEquals(0, $sancion->partidos_cumplidos);

        // Cierre real del partido 1 (1 < 2 => sigue Vigente)
        $cedulaJ2 = $this->cedulaService->abrir($this->partidoSiguiente);
        $this->cedulaService->guardarAlineacion($cedulaJ2, $capitanLocal->id, ['asistio' => true, 'es_capitan' => true], (string) Str::uuid());
        $this->cedulaService->guardarAlineacion($cedulaJ2, $capitanVisitante->id, ['asistio' => true, 'es_capitan' => true], (string) Str::uuid());
        $this->cierreCedulaService->firmar($cedulaJ2, 'local', $this->png());
        $this->cierreCedulaService->firmar($cedulaJ2, 'visitante', $this->png());
        $this->cierreCedulaService->cerrar($cedulaJ2->fresh(), $this->adminUser);

        $sancion->refresh();
        $this->assertEquals(1, $sancion->partidos_cumplidos);
        $this->assertEquals(1, $sancion->partidosRestantes());
        $this->assertEquals(Sancion::ESTADO_VIGENTE, $sancion->estado);

        // Partido 2 (2 == 2 => transiciona a Cumplida)
        $jornada3 = Jornada::create([
            'grupo_id' => $this->partido->jornada->grupo_id,
            'numero_jornada' => 3,
            'fecha_inicio' => '2026-11-28',
            'fecha_fin' => '2026-11-29',
        ]);
        $partidoJ3 = Partido::create([
            'jornada_id' => $jornada3->id,
            'equipo_local_id' => $this->partido->equipo_local_id,
            'equipo_visitante_id' => $this->partido->equipo_visitante_id,
            'arbitro_id' => $this->partido->arbitro_id,
            'fecha_partido' => '2026-11-28',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_CONFIRMADO,
        ]);

        $this->vigenciaService->computarPartidoJugado($partidoJ3);

        $sancion->refresh();
        $this->assertEquals(2, $sancion->partidos_cumplidos);
        $this->assertEquals(0, $sancion->partidosRestantes());
        $this->assertEquals(Sancion::ESTADO_CUMPLIDA, $sancion->estado);
        $this->assertTrue($sancion->estaCumplida());
    }

    /**
     * Recomendados + PO #49: Sanción de expediente sin partido_id (nulo) y traspaso a nueva temporada se descuentan correctamente.
     */
    public function test_descuenta_sancion_de_expediente_sin_partido_y_en_siguiente_temporada(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

        $expedienteManual = ExpedienteDisciplinario::create([
            'folio' => 'EXP-2026-MANUAL-99',
            'partido_id' => null,
            'articulo_id' => $this->articulo->id,
            'titulo' => 'Expediente sin partido asociado',
            'descripcion_hechos' => 'Reporte disciplinario administrativo sin partido origen.',
            'origen' => 'comision',
            'estado' => ExpedienteDisciplinario::ESTADO_RESUELTO,
            'fecha_apertura' => now(),
            'generado_automaticamente' => false,
        ]);

        $sancion = Sancion::create([
            'expediente_id' => $expedienteManual->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-11-14',
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        $this->vigenciaService->computarPartidoJugado($this->partidoSiguiente);
        $this->assertSame(1, $sancion->fresh()->partidos_cumplidos);

        // PO #49: Nuevo partido en nueva temporada descuenta el segundo partido restante del jugador
        $partidoNuevaTemp = $this->partidoConArbitro();
        $nuevoEquipo = $partidoNuevaTemp->local;
        Jugador::create([
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $nuevoEquipo->id,
            'dorsal' => 77,
            'estado_elegibilidad' => 'Activo',
        ]);
        $partidoNuevaTemp->update(['fecha_partido' => '2027-01-15']);

        $this->vigenciaService->computarPartidoJugado($partidoNuevaTemp->fresh());
        $sancion->refresh();
        $this->assertSame(2, $sancion->partidos_cumplidos);
        $this->assertSame(Sancion::ESTADO_CUMPLIDA, $sancion->estado);
    }

    /**
     * HU-046 CA2 / Obligatorio 6: Sanciones cumplidas, temporales vencidas, multas y amonestaciones NO aparecen en boletín activo.
     */
    public function test_sancion_cumplida_temporal_vencida_y_no_bloqueantes_salen_del_boletin(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)
            ->where('estado_elegibilidad', 'Activo')
            ->firstOrFail();

        $sancionPartidos = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Suspensión de 1 partido',
            'partidos_suspendidos' => 1,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-11-14',
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_publicacion' => now(),
        ]);

        // Suspensión temporal vencida (ayer)
        $ayer = Carbon::yesterday(Sancion::ZONA_HORARIA)->toDateString();
        $hace10Dias = Carbon::today(Sancion::ZONA_HORARIA)->subDays(10)->toDateString();
        $sancionTemporalVencida = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_TEMPORAL,
            'partidos_suspendidos' => 0,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => $hace10Dias,
            'fecha_fin' => $ayer,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_publicacion' => now(),
        ]);

        // Suspensión temporal cuyo último día es HOY (sí sigue vigente e inhabilita hoy)
        $hoy = Carbon::today(Sancion::ZONA_HORARIA)->toDateString();
        $sancionTemporalHoy = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_TEMPORAL,
            'partidos_suspendidos' => 0,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => $hace10Dias,
            'fecha_fin' => $hoy,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_publicacion' => now(),
        ]);

        // Multa publicada/vigente no debe figurar en castigados
        $multa = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_MULTA,
            'monto_multa' => 1200.00,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_publicacion' => now(),
        ]);

        $boletinInicial = $this->vigenciaService->obtenerBoletinCastigados();
        $this->assertTrue($boletinInicial->contains('id', $sancionPartidos->id));
        $this->assertTrue($boletinInicial->contains('id', $sancionTemporalHoy->id));
        $this->assertFalse($boletinInicial->contains('id', $sancionTemporalVencida->id));
        $this->assertFalse($boletinInicial->contains('id', $multa->id));

        // Cumplir partido => también sale del boletín
        $this->vigenciaService->computarPartidoJugado($this->partidoSiguiente);
        $sancionPartidos->refresh();
        $this->assertTrue($sancionPartidos->estaCumplida());

        $boletinFinal = $this->vigenciaService->obtenerBoletinCastigados();
        $this->assertFalse($boletinFinal->contains('id', $sancionPartidos->id));
    }

    /**
     * Obligatorio 8: Vigencia y boletín usan America/Mexico_City aunque PHP/UTC ya esté en el día siguiente.
     */
    public function test_vigencia_y_boletin_usan_zona_horaria_mexico_sobre_utc(): void
    {
        // 21/11/2026 03:15 UTC == 20/11/2026 21:15 America/Mexico_City
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 3, 15, 0, 'UTC'));

        try {
            $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)
                ->where('estado_elegibilidad', 'Activo')
                ->firstOrFail();

            ExpedienteInvolucrado::create([
                'expediente_id' => $this->expediente->id,
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $this->partido->equipo_local_id,
                'tipo_involucrado' => 'jugador_expulsado',
            ]);

            $resolucion = app(DisciplinaResolucionService::class);
            $sancion = $resolucion->dictaminarSancion($this->expediente, [
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $this->partido->equipo_local_id,
                'tipo_sancion' => Sancion::TIPO_TEMPORAL,
                'fecha_fin' => '2026-11-25',
                'validar_inmediatamente' => true,
                'publicar_en_boletin' => true,
                'motivo_resolucion' => 'Sanción dictaminada a las 21:15 hora de México.',
            ], $this->tribunalUser);

            // Debe tomar 2026-11-20 (hora México), NO 2026-11-21 (UTC)
            $this->assertSame('2026-11-20', $sancion->fecha_inicio?->toDateString());

            // Debe inhabilitar hoy 20/11
            $this->assertSame(1, Sancion::query()->whereKey($sancion->id)->inhabilitantes()->count());

            // Boletín debe mostrar la hora de México (21:15), no UTC (03:15)
            $response = $this->actingAs($this->tribunalUser)->get(route('disciplina.boletin.index'));
            $response->assertOk();
            $response->assertSee('20/11/2026 21:15');
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * RNF-44 / HU-046 CA3: Las sanciones no validadas o no publicadas no aparecen ni se publican.
     */
    public function test_rnf44_sanciones_sin_validar_no_aparecen_ni_se_publican_en_boletin(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();

        $sancionNoValidada = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Sanción pendiente de validación',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => false,
        ]);

        // Publicación sin validar rechazada con RNF-44
        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.boletin.publicar'), [
                'sanciones_ids' => [$sancionNoValidada->id],
                'motivo' => 'Intento sin validar previamente',
            ]);

        $response->assertSessionHasErrors('sanciones_ids');
        $this->assertFalse($sancionNoValidada->fresh()->publicada_en_boletin);
        $this->assertFalse($this->vigenciaService->obtenerBoletinCastigados()->contains('id', $sancionNoValidada->id));
    }

    /**
     * RNF-44: Publicación autorizada deriva el cargo del rol del usuario (no spoof) e impide sobrescribir autorizador.
     */
    public function test_publicacion_en_boletin_deriva_cargo_del_rol_e_impide_republicar(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();

        $sancion = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Sanción validada lista para publicación',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => false,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
        ]);

        $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.publicar-boletin', $sancion), [
                'autorizado_por_cargo' => 'Cargo Inventado Spoof',
                'motivo' => 'Autorización oficial para publicación en boletín semanal',
            ])
            ->assertRedirect();

        $sancion->refresh();
        $this->assertTrue($sancion->publicada_en_boletin);
        $this->assertEquals($this->tribunalUser->id, $sancion->autorizado_por_usuario_id);
        $this->assertSame('Tribunal de Penas', $sancion->autorizado_por_cargo);

        // No debe sobrescribir autorizador si otro usuario intenta republicarla
        $this->actingAs($this->adminUser)
            ->post(route('disciplina.boletin.publicar'), [
                'sanciones_ids' => [$sancion->id],
                'motivo' => 'Reintento de publicación',
            ])
            ->assertSessionHasErrors('sanciones_ids');
        $this->assertEquals($this->tribunalUser->id, $sancion->fresh()->autorizado_por_usuario_id);

        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $this->tribunalUser->id,
            'motivo' => 'Autorización oficial para publicación en boletín semanal',
        ]);
    }

    /**
     * HU-046 / RNF-44: Despublicación en ruta única retira sanción del boletín.
     */
    public function test_despublicacion_retira_sancion_del_boletin(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();

        $sancion = Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Sanción publicada a retirar',
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_publicacion' => now(),
        ]);

        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.despublicar', $sancion), [
                'motivo' => 'Retiro preventivo por apelación concedida',
            ]);

        $response->assertRedirect();

        $sancion->refresh();
        $this->assertFalse($sancion->publicada_en_boletin);
        $this->assertFalse($this->vigenciaService->obtenerBoletinCastigados()->contains('id', $sancion->id));
    }

    /**
     * PO #45 + Obligatorio 3: Visibilidad granular por rol del boletín interno y bloqueo 403 a roles sin acceso.
     */
    public function test_boletin_interno_aplica_autorizacion_y_alcance_por_rol_segun_po45(): void
    {
        $jugadorLocal = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();
        $jugadorVisitante = Jugador::where('equipo_id', $this->partido->equipo_visitante_id)->firstOrFail();
        $temporadaId = $this->partido->jornada->grupo->categoria->temporada_id;

        // Sanción validada y publicada del club local
        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugadorLocal->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Sanción visible club local',
            'partidos_suspendidos' => 3,
            'partidos_cumplidos' => 1,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'autorizado_por_cargo' => 'Tribunal de Penas',
            'fecha_publicacion' => now(),
        ]);

        // Sanción validada del club visitante (otro club)
        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugadorVisitante->persona_id,
            'equipo_id' => $this->partido->equipo_visitante_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'partidos_suspendidos' => 4,
            'partidos_cumplidos' => 0,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'autorizado_por_cargo' => 'Tribunal de Penas',
            'fecha_publicacion' => now(),
        ]);

        // 1. Delegado del Club: ve las validadas de su club, NO las del club visitante, NO CURP, NO panel
        $resDelegado = $this->actingAs($this->delegadoUser)
            ->get(route('disciplina.boletin.index', ['temporada_id' => $temporadaId]));
        $resDelegado->assertOk();
        $resDelegado->assertSee($jugadorLocal->persona->nombre);
        $resDelegado->assertSee('2 restantes');
        $resDelegado->assertSee('Faltan 2 partidos');
        $resDelegado->assertDontSee($jugadorVisitante->persona->apellidos);
        $resDelegado->assertDontSee($jugadorLocal->persona->curp);
        $resDelegado->assertDontSee('Sanciones Vigentes Pendientes de Publicación Oficial');

        // 2. Jugador: ve únicamente sus propias sanciones validadas
        $usuarioJugador = $this->usuario('Jugador');
        $usuarioJugador->forceFill(['persona_id' => $jugadorLocal->persona_id])->save();
        $resJugador = $this->actingAs($usuarioJugador)->get(route('disciplina.boletin.index'));
        $resJugador->assertOk();
        $resJugador->assertSee($jugadorLocal->persona->nombre);
        $resJugador->assertDontSee($jugadorVisitante->persona->apellidos);

        // 3. Tribunal: ve todas + panel administrativo
        $resTribunal = $this->actingAs($this->tribunalUser)->get(route('disciplina.boletin.index'));
        $resTribunal->assertOk();
        $resTribunal->assertSee($jugadorLocal->persona->apellidos);
        $resTribunal->assertSee($jugadorVisitante->persona->apellidos);

        // 4. Roles sin acceso al boletín interno (Árbitro, Programador, Capturista, Tesorero, etc.): 403 en español
        foreach ([
            $this->usuario('Árbitro en Campo', $this->partido->arbitro),
            $this->usuario('Programador de Partidos'),
            $this->usuario('Capturista'),
            $this->usuario('Comisión Arbitral'),
            $this->usuario('Presidente'),
            $this->usuario('Secretario'),
            $this->usuario('Tesorero'),
        ] as $sinAcceso) {
            $res = $this->actingAs($sinAcceso)->get(route('disciplina.boletin.index'));
            $res->assertForbidden();
            $this->assertStringContainsString('boletín interno de sanciones', $res->getContent());
        }
    }

    private function png(int $ancho = 60, int $alto = 40): string
    {
        $bloque = fn (string $tipo, string $datos): string => pack('N', strlen($datos)).$tipo.$datos.pack('N', crc32($tipo.$datos));
        $filas = str_repeat("\x00".str_repeat("\xff\xff\xff", $ancho), $alto);

        return "\x89PNG\r\n\x1a\n"
            .$bloque('IHDR', pack('NNCCCCC', $ancho, $alto, 8, 2, 0, 0, 0))
            .$bloque('IDAT', (string) gzcompress($filas))
            .$bloque('IEND', '');
    }
}
