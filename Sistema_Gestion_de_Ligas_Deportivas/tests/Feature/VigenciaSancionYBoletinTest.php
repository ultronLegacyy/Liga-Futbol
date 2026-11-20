<?php

namespace Tests\Feature;

use App\Models\CatalogoArticulo;
use App\Models\ExpedienteDisciplinario;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use App\Services\CedulaService;
use App\Services\CierreCedulaService;
use App\Services\VigenciaSancionService;
use Database\Seeders\CatalogoSancionesArticulosSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/**
 * Pruebas de integración para SCRUM-58 / HU-046 / RF-10.3, RNF-44:
 * Cálculo de vigencia de sanciones, descuento automático de partidos tras cierre real de cédula,
 * y publicación autorizada e inalterable en el Boletín Oficial de Castigados.
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
     * RF-10.3 Criterio 1: Creación de sanción con cómputo de partidos pendientes y vigencia calculada hasta partido futuro.
     */
    public function test_calculo_vigencia_inicial_proyecta_partidos_y_estado_vigente(): void
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
        $this->assertSame($this->partidoSiguiente->id, $sancion->partido_suspension_hasta_id);
        $this->assertSame('2026-11-21', $sancion->fecha_fin?->toDateString());
    }

    /**
     * RF-10.3 Criterio 2: El cierre real de la cédula en un partido posterior computa y descuenta partidos jugados automáticamente,
     * transicionando a Cumplida al finalizar el castigo.
     */
    public function test_cierre_real_de_cedula_computa_y_descuenta_partido_jugado_progresivamente(): void
    {
        // Jugador 1 queda sancionado (no alinea); otro compañero activo cumple como capitán
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
            'autorizado_por_cargo' => 'Presidente Comisión',
            'fecha_publicacion' => now(),
        ]);

        // Cerrar el partido origen NO debe descontar la propia sanción originada en ese partido
        $this->vigenciaService->computarPartidoJugado($this->partido);
        $sancion->refresh();
        $this->assertEquals(0, $sancion->partidos_cumplidos);

        // Abrir y cerrar realmente la cédula del partido siguiente (Jornada 2) mediante CierreCedulaService
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

        // Segundo partido posterior oficial (Jornada 3)
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
     * RF-10.3 Criterio 3: Sanciones cumplidas se retiran automáticamente del boletín público oficial.
     */
    public function test_sancion_cumplida_no_aparece_en_boletin_oficial(): void
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

        $boletinInicial = $this->vigenciaService->obtenerBoletinCastigados();
        $this->assertTrue($boletinInicial->contains('id', $sancion->id));

        // Computar partido siguiente para cumplir la sanción
        $this->vigenciaService->computarPartidoJugado($this->partidoSiguiente);

        $sancion->refresh();
        $this->assertTrue($sancion->estaCumplida());

        $boletinFinal = $this->vigenciaService->obtenerBoletinCastigados();
        $this->assertFalse($boletinFinal->contains('id', $sancion->id));
    }

    /**
     * RNF-44: Las sanciones no validadas o no publicadas no aparecen en el boletín y no pueden publicarse sin validación previa.
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

        // Intento de publicar sin validar previamente debe ser rechazado por RNF-44
        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.boletin.publicar'), [
                'sanciones_ids' => [$sancionNoValidada->id],
                'autorizado_por_cargo' => 'Presidente Comisión',
                'motivo' => 'Intento sin validar previamente',
            ]);

        $response->assertSessionHasErrors('sanciones_ids');

        $sancionNoValidada->refresh();
        $this->assertFalse($sancionNoValidada->publicada_en_boletin);

        $boletin = $this->vigenciaService->obtenerBoletinCastigados();
        $this->assertFalse($boletin->contains('id', $sancionNoValidada->id));
    }

    /**
     * RNF-44: Publicación autorizada de sanción validada registra al autorizador y cargo de forma inalterable y genera auditoría.
     */
    public function test_publicacion_en_boletin_registra_autorizador_y_cargo_de_forma_inalterable(): void
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

        $response = $this->actingAs($this->tribunalUser)
            ->post(route('disciplina.sanciones.publicar-boletin', $sancion), [
                'autorizado_por_cargo' => 'Presidente del Tribunal de Penas',
                'motivo' => 'Autorización oficial para publicación en boletín semanal',
            ]);

        $response->assertRedirect();

        $sancion->refresh();
        $this->assertTrue($sancion->publicada_en_boletin);
        $this->assertEquals($this->tribunalUser->id, $sancion->autorizado_por_usuario_id);
        $this->assertSame('Presidente del Tribunal de Penas', $sancion->autorizado_por_cargo);
        $this->assertNotNull($sancion->fecha_publicacion);

        // Verificar registro de auditoría inmutable
        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $this->tribunalUser->id,
            'motivo' => 'Autorización oficial para publicación en boletín semanal',
        ]);
    }

    /**
     * RF-10.3 / RNF-44: Retiro / despublicación de sanción del boletín oficial.
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
            ->post(route('disciplina.sanciones.despublicar-boletin', $sancion), [
                'motivo' => 'Retiro preventivo por apelación concedida',
            ]);

        $response->assertRedirect();

        $sancion->refresh();
        $this->assertFalse($sancion->publicada_en_boletin);

        $boletin = $this->vigenciaService->obtenerBoletinCastigados();
        $this->assertFalse($boletin->contains('id', $sancion->id));
    }

    /**
     * RF-10.3: Vista del boletín filtra por temporada y oculta CURP y panel administrativo a delegados.
     */
    public function test_vista_boletin_filtra_por_temporada_y_protege_datos_y_panel_a_delegado(): void
    {
        $jugador = Jugador::where('equipo_id', $this->partido->equipo_local_id)->firstOrFail();
        $temporadaId = $this->partido->jornada->grupo->categoria->temporada_id;

        Sancion::create([
            'expediente_id' => $this->expediente->id,
            'persona_id' => $jugador->persona_id,
            'equipo_id' => $this->partido->equipo_local_id,
            'articulo_id' => $this->articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'descripcion_sancion' => 'Sanción visible en boletín',
            'partidos_suspendidos' => 3,
            'partidos_cumplidos' => 1,
            'estado' => Sancion::ESTADO_VIGENTE,
            'publicada_en_boletin' => true,
            'validado_por_usuario_id' => $this->tribunalUser->id,
            'fecha_validacion' => now(),
            'autorizado_por_usuario_id' => $this->tribunalUser->id,
            'autorizado_por_cargo' => 'Secretario del Tribunal',
            'fecha_publicacion' => now(),
        ]);

        // Un Delegado consulta con filtro de temporada
        $response = $this->actingAs($this->delegadoUser)
            ->get(route('disciplina.boletin.index', ['temporada_id' => $temporadaId]));

        $response->assertOk();
        $response->assertSee('Boletín Oficial de Sancionados y Castigados');
        $response->assertSee($this->expediente->folio);
        $response->assertSee($jugador->persona->nombre);
        $response->assertSee('2 restantes');
        $response->assertSee('Secretario del Tribunal');
        $response->assertDontSee($jugador->persona->curp);
        $response->assertDontSee('Sanciones Vigentes Pendientes de Publicación Oficial');
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
