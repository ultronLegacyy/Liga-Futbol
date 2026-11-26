<?php

namespace Tests\Feature;

use App\Jobs\ProgramarCalendarioMasivoJob;
use App\Models\AuditoriaLog;
use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\CatalogoArticulo;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\ExpedienteDisciplinario;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\PreferenciaEquipo;
use App\Models\Sancion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\GeneradorEnfrentamientosService;
use App\Services\ProgramacionAutomaticaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pruebas integrales de asignación automática de horarios (SCRUM-63 / HU-074)
 * y detección/explicación de conflictos no bloqueantes (SCRUM-64 / HU-033).
 *
 * Cubre: RF-07.2, RF-07.3, RNF-07, RNF-08, RNF-19 y RBAC.
 */
class ProgramacionAutomaticaYConflictosTest extends TestCase
{
    use DatabaseTransactions;

    private User $programador;

    private User $usuarioSinPermiso;

    private Temporada $temporada;

    private Categoria $categoria;

    private Grupo $grupo;

    private GeneradorEnfrentamientosService $generadorService;

    private ProgramacionAutomaticaService $programacionService;

    private int $secuenciaClub = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->programador = $this->crearUsuario('programador_auto@liga.com', 'Programador de Partidos');
        $this->usuarioSinPermiso = $this->crearUsuario('tesorero_auto@liga.com', 'Tesorero');

        $this->temporada = Temporada::factory()->activa()->create([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2028-12-31',
        ]);
        $this->categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create([
            'categoria_id' => $this->categoria->id,
            'temporada_id' => $this->temporada->id,
        ]);

        $this->generadorService = app(GeneradorEnfrentamientosService::class);
        $this->programacionService = app(ProgramacionAutomaticaService::class);
    }

    public function test_asigna_horarios_respetando_bloqueos_canchas_inactivas_y_empalmes_existentes(): void
    {
        $e1 = $this->crearEquipo('Águilas');
        $e2 = $this->crearEquipo('Halcones');
        $e3 = $this->crearEquipo('Tigres');
        $e4 = $this->crearEquipo('Pumas');

        $canchaMantenimiento = Cancha::factory()->create(['nombre' => 'Cancha Mantenimiento', 'estado' => 'Mantenimiento', 'capacidad_partidos_consecutivos' => 5]);
        $canchaInactiva = Cancha::factory()->create(['nombre' => 'Cancha Inactiva', 'estado' => 'Inactiva', 'capacidad_partidos_consecutivos' => 5]);
        $canchaDisponible = Cancha::factory()->create(['nombre' => 'Cancha Principal', 'estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);

        // Jornada 1: 2026-08-01 (sábado único)
        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-01',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);
        Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e3->id,
            'equipo_visitante_id' => $e4->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Bloqueo en la franja de 09:00 a 11:00 sobre la cancha disponible
        BloqueoCancha::create([
            'cancha_id' => $canchaDisponible->id,
            'fecha_inicio' => '2026-08-01 09:00:00',
            'fecha_fin' => '2026-08-01 11:00:00',
            'motivo' => 'Mantenimiento de césped matutino',
        ]);

        // Empalme de otro grupo ocupando 11:00 a 13:00 en Cancha Principal
        $otroGrupo = Grupo::factory()->create(['categoria_id' => $this->categoria->id, 'temporada_id' => $this->temporada->id]);
        $e5 = $this->crearEquipo('Externo A', $otroGrupo);
        $e6 = $this->crearEquipo('Externo B', $otroGrupo);
        $otraJornada = Jornada::create([
            'grupo_id' => $otroGrupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-01',
            'estado' => Jornada::ESTADO_PUBLICADA,
        ]);
        Partido::create([
            'jornada_id' => $otraJornada->id,
            'equipo_local_id' => $e5->id,
            'equipo_visitante_id' => $e6->id,
            'cancha_id' => $canchaDisponible->id,
            'fecha_partido' => '2026-08-01',
            'hora_partido' => '11:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $this->actingAs($this->programador);
        $resumen = $this->programacionService->asignarHorariosAJornada($jornada, [
            'horarios' => ['09:00', '11:00', '13:00', '15:00'],
        ]);

        $this->assertSame(2, $resumen['total_partidos']);
        $this->assertSame(2, $resumen['asignados']);
        $this->assertTrue($resumen['completado']);

        $partidos = $jornada->partidos()->orderBy('hora_partido')->get();
        $horas = $partidos->pluck('hora_partido')->all();
        $this->assertSame(['13:00:00', '15:00:00'], $horas);

        foreach ($partidos as $partido) {
            $this->assertSame($canchaDisponible->id, $partido->cancha_id);
            $this->assertNotEquals($canchaMantenimiento->id, $partido->cancha_id);
            $this->assertNotEquals($canchaInactiva->id, $partido->cancha_id);
        }

        $this->assertTrue(
            AuditoriaLog::query()
                ->where('tabla_afectada', 'partidos')
                ->where('motivo', 'like', '%Asignación automática de horarios%')
                ->exists()
        );
    }

    public function test_excluye_equipo_con_sancion_colectiva_vigente_e_ignora_sancion_individual_de_jugador(): void
    {
        $e1 = $this->crearEquipo('Sancionado FC');
        $e2 = $this->crearEquipo('Rival Limpio');
        $e3 = $this->crearEquipo('Con Jugador Suspendido');
        $e4 = $this->crearEquipo('Invitado FC');

        Cancha::factory()->create(['estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);

        $articulo = CatalogoArticulo::create([
            'codigo' => 'ART-EQ-01',
            'descripcion' => 'Suspensión temporal de actividad competitiva al club/equipo por incumplimiento.',
            'castigo_base_partidos' => 2,
        ]);

        $expediente = ExpedienteDisciplinario::create([
            'folio' => 'EXP-EQ-AUTO-01',
            'partido_id' => null,
            'articulo_id' => $articulo->id,
            'titulo' => 'Suspensión colectiva administrativa de equipo',
            'descripcion_hechos' => 'Incumplimiento administrativo vigente del club.',
            'origen' => 'comision',
            'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO,
        ]);

        // Sanción colectiva al equipo e1 (persona_id = null)
        Sancion::create([
            'expediente_id' => $expediente->id,
            'equipo_id' => $e1->id,
            'persona_id' => null,
            'articulo_id' => $articulo->id,
            'tipo_sancion' => Sancion::TIPO_TEMPORAL,
            'partidos_suspendidos' => 0,
            'partidos_cumplidos' => 0,
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-30',
            'motivo' => 'Adeudo disciplinario vigente del club.',
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        // Sanción individual de un jugador de e3 (NO debe bloquear al equipo completo)
        $personaJugador = Persona::factory()->create();
        Sancion::create([
            'expediente_id' => $expediente->id,
            'equipo_id' => $e3->id,
            'persona_id' => $personaJugador->id,
            'articulo_id' => $articulo->id,
            'tipo_sancion' => Sancion::TIPO_PARTIDOS,
            'partidos_suspendidos' => 2,
            'partidos_cumplidos' => 0,
            'motivo' => 'Doble amonestación en jornada previa.',
            'estado' => Sancion::ESTADO_VIGENTE,
        ]);

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-08',
            'fecha_fin' => '2026-08-08',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partidoBloqueado = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);
        $partidoValido = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e3->id,
            'equipo_visitante_id' => $e4->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $resumen = $this->programacionService->asignarHorariosAJornada($jornada);

        $this->assertSame(2, $resumen['total_partidos']);
        $this->assertSame(1, $resumen['asignados']);
        $this->assertSame(1, $resumen['sin_asignar']);
        $this->assertCount(1, $resumen['conflictos']);

        $conflicto = $resumen['conflictos'][0];
        $this->assertSame('SANCION_EQUIPO_VIGENTE', $conflicto['codigo_regla']);
        $this->assertStringContainsString('RF-07.2', $conflicto['regla_origen']);
        $this->assertStringContainsString('Sancionado FC', $conflicto['causa']);

        $partidoBloqueado->refresh();
        $partidoValido->refresh();
        $this->assertNull($partidoBloqueado->cancha_id);
        $this->assertNull($partidoBloqueado->hora_partido);
        $this->assertNotNull($partidoValido->cancha_id);
        $this->assertNotNull($partidoValido->hora_partido);
    }

    public function test_prioriza_preferencias_blandas_de_equipo_y_usa_alternativa_si_hay_bloqueo(): void
    {
        $local = $this->crearEquipo('Preferido FC');
        $visitante = $this->crearEquipo('Visitante Estándar');

        $cancha1 = Cancha::factory()->create(['nombre' => 'Cancha Norte', 'estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);
        $canchaPreferida = Cancha::factory()->create(['nombre' => 'Cancha Estadio', 'estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);

        PreferenciaEquipo::create([
            'equipo_id' => $local->id,
            'cancha_id_preferida' => $canchaPreferida->id,
            'dia_preferido' => 'Domingo',
            'hora_preferida' => '13:00:00',
        ]);

        // 2026-08-08 = Sábado, 2026-08-09 = Domingo
        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-08',
            'fecha_fin' => '2026-08-09',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partido = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $local->id,
            'equipo_visitante_id' => $visitante->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $resumen1 = $this->programacionService->asignarHorariosAJornada($jornada, [
            'horarios' => ['09:00', '13:00'],
        ]);

        $this->assertSame(1, $resumen1['preferencias_respetadas']);
        $partido->refresh();
        $this->assertSame($canchaPreferida->id, $partido->cancha_id);
        $this->assertSame('2026-08-09', $partido->fecha_partido?->toDateString());
        $this->assertSame('13:00:00', $partido->hora_partido);

        // Si se bloquea toda la cancha preferida el domingo a las 13:00, el motor cede a la alternativa sin fallar
        BloqueoCancha::create([
            'cancha_id' => $canchaPreferida->id,
            'fecha_inicio' => '2026-08-09 12:00:00',
            'fecha_fin' => '2026-08-09 16:00:00',
            'motivo' => 'Torneo relámpago infantil',
        ]);

        $resumen2 = $this->programacionService->asignarHorariosAJornada($jornada, [
            'horarios' => ['09:00', '13:00'],
            'reemplazar_existentes' => true,
        ]);

        $this->assertSame(1, $resumen2['asignados']);
        $partido->refresh();
        $this->assertNotNull($partido->cancha_id);
        // Sigue priorizando cancha preferida en otro horario o día preferido en Cancha Norte
        $this->assertTrue(
            $partido->cancha_id === $canchaPreferida->id || $partido->fecha_partido?->toDateString() === '2026-08-09',
            "Debe aprovechar preferencia parcial o alternativa válida ({$cancha1->id})."
        );
    }

    public function test_evita_empalme_horario_de_un_mismo_equipo_en_horarios_simultaneos(): void
    {
        $equipoCompartido = $this->crearEquipo('Multitorneo FC');
        $rival1 = $this->crearEquipo('Rival Uno');

        $otroGrupo = Grupo::factory()->create(['categoria_id' => $this->categoria->id, 'temporada_id' => $this->temporada->id]);
        $rival2 = $this->crearEquipo('Rival Dos', $otroGrupo);

        $canchaA = Cancha::factory()->create(['estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);
        $canchaB = Cancha::factory()->create(['estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);

        $jornadaOtra = Jornada::create([
            'grupo_id' => $otroGrupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-15',
            'fecha_fin' => '2026-08-15',
            'estado' => Jornada::ESTADO_PUBLICADA,
        ]);
        Partido::create([
            'jornada_id' => $jornadaOtra->id,
            'equipo_local_id' => $equipoCompartido->id,
            'equipo_visitante_id' => $rival2->id,
            'cancha_id' => $canchaB->id,
            'fecha_partido' => '2026-08-15',
            'hora_partido' => '09:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $jornadaLocal = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-15',
            'fecha_fin' => '2026-08-15',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);
        $partidoLocal = Partido::create([
            'jornada_id' => $jornadaLocal->id,
            'equipo_local_id' => $equipoCompartido->id,
            'equipo_visitante_id' => $rival1->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $resumen = $this->programacionService->asignarHorariosAJornada($jornadaLocal, [
            'cancha_ids' => [$canchaA->id],
            'horarios' => ['09:00', '11:00'],
        ]);

        $this->assertSame(1, $resumen['asignados']);
        $partidoLocal->refresh();
        $this->assertSame('11:00:00', $partidoLocal->hora_partido);
    }

    public function test_hu033_reporta_deficit_capacidad_sin_bloquear_y_detalla_regla_origen_en_json_y_vista(): void
    {
        $e1 = $this->crearEquipo('Equipo 1');
        $e2 = $this->crearEquipo('Equipo 2');
        $e3 = $this->crearEquipo('Equipo 3');
        $e4 = $this->crearEquipo('Equipo 4');
        $e5 = $this->crearEquipo('Equipo 5');
        $e6 = $this->crearEquipo('Equipo 6');

        // Solo 1 cancha disponible con capacidad máxima de 2 partidos diarios para 3 partidos
        Cancha::factory()->create([
            'nombre' => 'Cancha Única',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 2,
        ]);

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-09-05',
            'fecha_fin' => '2026-09-05',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        Partido::create(['jornada_id' => $jornada->id, 'equipo_local_id' => $e1->id, 'equipo_visitante_id' => $e2->id, 'estado' => Partido::ESTADO_BORRADOR]);
        Partido::create(['jornada_id' => $jornada->id, 'equipo_local_id' => $e3->id, 'equipo_visitante_id' => $e4->id, 'estado' => Partido::ESTADO_BORRADOR]);
        Partido::create(['jornada_id' => $jornada->id, 'equipo_local_id' => $e5->id, 'equipo_visitante_id' => $e6->id, 'estado' => Partido::ESTADO_BORRADOR]);

        $respuesta = $this->actingAs($this->programador)
            ->postJson(route('jornadas.asignar-horarios', $jornada), [
                'horarios' => ['09:00', '11:00', '13:00'],
            ])
            ->assertOk()
            ->json();

        // No bloquea: guarda 2 válidos y reporta el 3ero como déficit explicado (HU-033 CA1 & CA2)
        $this->assertSame(3, $respuesta['total_partidos']);
        $this->assertSame(2, $respuesta['asignados']);
        $this->assertSame(1, $respuesta['sin_asignar']);
        $this->assertFalse($respuesta['completado']);
        $this->assertSame('CANCHA_SIN_CAPACIDAD', $respuesta['conflictos'][0]['codigo_regla']);
        $this->assertStringContainsString('RF-07.2', $respuesta['conflictos'][0]['regla_origen']);
        $this->assertStringContainsString('tope', $respuesta['conflictos'][0]['causa']);
        $this->assertNotEmpty($respuesta['conflictos'][0]['sugerencia']);

        // Endpoint JSON de conflictos (HU-033 + RNF-19)
        $jsonConflictos = $this->actingAs($this->programador)
            ->getJson(route('jornadas.conflictos', $jornada))
            ->assertOk()
            ->json();

        $this->assertTrue($jsonConflictos['tiene_conflictos']);
        $this->assertNotEmpty($jsonConflictos['conflictos_detallados']);
        $this->assertSame('CANCHA_SIN_CAPACIDAD', $jsonConflictos['conflictos_detallados'][0]['codigo_regla']);

        // Vista show de la jornada muestra el detalle RNF-19
        $this->actingAs($this->programador)
            ->get(route('jornadas.show', $jornada))
            ->assertOk()
            ->assertSee('Equipo 5 vs Equipo 6')
            ->assertSee('RF-07.2');
    }

    public function test_hu033_explica_sin_canchas_operativas_y_cancha_bloqueada(): void
    {
        $e1 = $this->crearEquipo('Lobo FC');
        $e2 = $this->crearEquipo('Zorro FC');

        $canchaMantenimiento = Cancha::factory()->create(['estado' => 'Mantenimiento']);

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-09-12',
            'fecha_fin' => '2026-09-12',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Caso 1: todas las canchas en mantenimiento / inactivas
        $resumenSinCanchas = $this->programacionService->asignarHorariosAJornada($jornada);
        $this->assertSame('SIN_CANCHAS_OPERATIVAS', $resumenSinCanchas['conflictos'][0]['codigo_regla']);

        // Caso 2: cancha habilitada pero bloqueada todo el día
        $canchaMantenimiento->estado = 'Disponible';
        $canchaMantenimiento->save();
        BloqueoCancha::create([
            'cancha_id' => $canchaMantenimiento->id,
            'fecha_inicio' => '2026-09-12 00:00:00',
            'fecha_fin' => '2026-09-12 23:59:59',
            'motivo' => 'Lluvia intensa e inundación en campo',
        ]);

        $resumenBloqueada = $this->programacionService->asignarHorariosAJornada($jornada);
        $this->assertSame('CANCHA_BLOQUEADA', $resumenBloqueada['conflictos'][0]['codigo_regla']);
        $this->assertStringContainsString('Lluvia intensa', $resumenBloqueada['conflictos'][0]['causa']);
    }

    public function test_flujo_http_previsualizar_y_confirmar_rol_con_asignacion_automatica(): void
    {
        $this->crearEquipo('Estrella 1');
        $this->crearEquipo('Estrella 2');
        $this->crearEquipo('Estrella 3');
        $this->crearEquipo('Estrella 4');

        $cancha = Cancha::factory()->create(['nombre' => 'Cancha Municipal', 'estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);

        $preview = $this->actingAs($this->programador)
            ->postJson(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => '2026-10-03',
                'intervalo_dias' => 7,
                'dias_duracion' => 1,
                'vueltas' => 1,
                'asignar_horarios' => true,
                'horarios' => ['09:00', '11:00'],
                'cancha_ids' => [$cancha->id],
            ])
            ->assertOk()
            ->json();

        $this->assertSame(6, $preview['resumen_horarios']['total_partidos']);
        $this->assertSame(6, $preview['resumen_horarios']['asignados']);
        $this->assertTrue($preview['resumen_horarios']['completado']);

        // Confirmar persistencia pasando asignar_horarios = true
        $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($preview),
                'asignar_horarios' => 1,
                'horarios' => ['09:00', '11:00'],
                'cancha_ids' => [$cancha->id],
            ])
            ->assertRedirect(route('grupos.jornadas.index', $this->grupo))
            ->assertSessionHas('success');

        $this->assertSame(3, $this->grupo->jornadas()->count());
        $partidos = Partido::query()->whereIn('jornada_id', $this->grupo->jornadas()->pluck('id'))->get();
        $this->assertCount(6, $partidos);
        $this->assertSame(6, $partidos->whereNotNull('cancha_id')->whereNotNull('hora_partido')->count());
    }

    public function test_rnf07_grupo_hasta_50_equipos_asigna_horarios_en_menos_de_10_segundos(): void
    {
        $this->crearEquiposEnLote($this->grupo, 50);

        for ($c = 1; $c <= 6; $c++) {
            Cancha::factory()->create([
                'nombre' => "Cancha Sede {$c}",
                'estado' => 'Disponible',
                'capacidad_partidos_consecutivos' => 5,
            ]);
        }

        $inicio = microtime(true);

        // 50 equipos -> 49 jornadas * 25 partidos = 1,225 partidos en 1 vuelta
        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-01-10',
            'intervalo_dias' => 7,
            'dias_duracion' => 1,
            'vueltas' => 1,
            'asignar_horarios' => true,
            'horarios' => ['08:00', '10:00', '12:00', '14:00', '16:00'],
        ]);

        $duracionSegundos = microtime(true) - $inicio;

        $this->assertLessThan(10.0, $duracionSegundos, "La programación de 50 equipos tardó {$duracionSegundos}s y excede el límite RNF-07 (10s).");
        $this->assertSame(1225, $propuesta['resumen_horarios']['total_partidos']);
        $this->assertSame(1225, $propuesta['resumen_horarios']['asignados']);
        $this->assertTrue($propuesta['resumen_horarios']['completado']);
    }

    public function test_rnf07_jornada_masiva_400_partidos_y_40_canchas_en_segundo_plano_bajo_60_segundos(): void
    {
        // Escenario real RNF-07: 10 grupos * 40 partidos = 400 partidos simultáneos y 40 canchas
        $canchas = [];
        for ($i = 1; $i <= 40; $i++) {
            $canchas[] = Cancha::factory()->create([
                'nombre' => "Campo Masivo {$i}",
                'estado' => 'Disponible',
                'capacidad_partidos_consecutivos' => 5,
            ]);
        }

        $ahora = now();
        $clubBase = Club::create(['nombre' => 'Club Masivo Base']);

        for ($g = 1; $g <= 10; $g++) {
            $grupoActual = Grupo::factory()->create([
                'categoria_id' => $this->categoria->id,
                'temporada_id' => $this->temporada->id,
                'nombre' => "Grupo Masivo {$g}",
            ]);

            $equipoIds = $this->crearEquiposEnLote($grupoActual, 80, $clubBase->id);
            $jornadaActual = Jornada::create([
                'grupo_id' => $grupoActual->id,
                'numero_jornada' => 1,
                'fecha_inicio' => '2026-11-07',
                'fecha_fin' => '2026-11-08',
                'estado' => Jornada::ESTADO_BORRADOR,
            ]);

            $lotePartidos = [];
            for ($p = 0; $p < 40; $p++) {
                $lotePartidos[] = [
                    'jornada_id' => $jornadaActual->id,
                    'equipo_local_id' => $equipoIds[$p * 2],
                    'equipo_visitante_id' => $equipoIds[($p * 2) + 1],
                    'fecha_partido' => '2026-11-07',
                    'hora_partido' => null,
                    'cancha_id' => null,
                    'arbitro_id' => null,
                    'estado' => Partido::ESTADO_BORRADOR,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
            DB::table('partidos')->insert($lotePartidos);
        }

        $inicio = microtime(true);

        (new ProgramarCalendarioMasivoJob($this->temporada->id, [
            'horarios' => ['08:00', '10:00', '12:00', '14:00', '16:00'],
            'cancha_ids' => array_map(fn (Cancha $c): int => $c->id, $canchas),
        ]))->handle($this->programacionService);

        $duracionSegundos = microtime(true) - $inicio;

        $this->assertLessThan(60.0, $duracionSegundos, "La programación masiva de 400 partidos tardó {$duracionSegundos}s y excede RNF-07 (60s).");

        $resumenCache = Cache::get("programacion_masiva_temporada_{$this->temporada->id}");
        $this->assertIsArray($resumenCache);
        $this->assertSame(400, $resumenCache['total_partidos']);
        $this->assertSame(400, $resumenCache['asignados']);
        $this->assertTrue($resumenCache['completado']);

        // Verificar que no exista ningún empalme de cancha en los 400 partidos persistidos
        $slotsOcupados = Partido::query()
            ->whereIn('jornada_id', Jornada::query()->whereHas('grupo', fn ($q) => $q->where('temporada_id', $this->temporada->id))->pluck('id'))
            ->get(['cancha_id', 'fecha_partido', 'hora_partido'])
            ->map(fn (Partido $p): string => $p->cancha_id.'|'.$p->fecha_partido?->toDateString().'|'.$p->hora_partido);

        $this->assertCount(400, $slotsOcupados->unique());

        // Encolado HTTP en segundo plano (en_segundo_plano = 1)
        Queue::fake();
        $this->actingAs($this->programador)
            ->postJson(route('temporadas.asignar-horarios-masivo', $this->temporada), [
                'en_segundo_plano' => true,
            ])
            ->assertStatus(202)
            ->assertJson(['enqueued' => true, 'temporada_id' => $this->temporada->id]);

        Queue::assertPushed(ProgramarCalendarioMasivoJob::class);
    }

    public function test_rbac_y_temporada_cerrada_rechazan_programacion_automatica(): void
    {
        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-10-10',
            'fecha_fin' => '2026-10-11',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        // Usuario sin permisos (Tesorero) -> 403 con mensaje en español
        $this->actingAs($this->usuarioSinPermiso)
            ->postJson(route('jornadas.asignar-horarios', $jornada))
            ->assertForbidden()
            ->assertJsonFragment(['mensaje' => 'No cuenta con privilegios para modificar jornadas.']);

        $this->actingAs($this->usuarioSinPermiso)
            ->postJson(route('grupos.asignar-horarios', $this->grupo))
            ->assertForbidden()
            ->assertJsonFragment(['mensaje' => 'No cuenta con privilegios para crear jornadas.']);

        $this->actingAs($this->usuarioSinPermiso)
            ->postJson(route('temporadas.asignar-horarios-masivo', $this->temporada))
            ->assertForbidden()
            ->assertJsonFragment(['mensaje' => 'No cuenta con privilegios para ejecutar la programación automática masiva.']);

        // Temporada cerrada -> rechazo en endpoint (403 en español) y servicio
        $this->temporada->update(['estado' => 'cerrada']);

        $this->actingAs($this->programador)
            ->postJson(route('jornadas.asignar-horarios', $jornada))
            ->assertForbidden()
            ->assertJsonFragment(['mensaje' => 'Una temporada cerrada no admite cambios en sus jornadas.']);

        $this->actingAs($this->programador)
            ->postJson(route('temporadas.asignar-horarios-masivo', $this->temporada))
            ->assertForbidden()
            ->assertJsonFragment(['mensaje' => 'Una temporada cerrada no admite cambios en su programación.']);

        $this->expectException(ValidationException::class);
        $this->programacionService->asignarHorariosMasivoTemporada($this->temporada->fresh());
    }

    public function test_reasignacion_total_limpia_horarios_previos_de_partidos_conflictivos_para_evitar_empalmes(): void
    {
        $e1 = $this->crearEquipo('Alpha');
        $e2 = $this->crearEquipo('Beta');
        $e3 = $this->crearEquipo('Gamma');
        $e4 = $this->crearEquipo('Delta');

        $cancha = Cancha::factory()->create([
            'nombre' => 'Cancha Central',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 1,
        ]);

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-10-17',
            'fecha_fin' => '2026-10-17',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        // Ambos partidos tenían previamente horarios asignados manualmente sobre la misma cancha
        $p1 = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'cancha_id' => $cancha->id,
            'fecha_partido' => '2026-10-17',
            'hora_partido' => '09:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);
        $p2 = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e3->id,
            'equipo_visitante_id' => $e4->id,
            'cancha_id' => $cancha->id,
            'fecha_partido' => '2026-10-17',
            'hora_partido' => '11:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Bloquear las 11:00 y reasignar todo con capacidad = 1: p1 queda en 09:00 y p2 debe limpiar su horario previo
        BloqueoCancha::create([
            'cancha_id' => $cancha->id,
            'fecha_inicio' => '2026-10-17 11:00:00',
            'fecha_fin' => '2026-10-17 13:00:00',
            'motivo' => 'Riego programado',
        ]);

        $resumen = $this->programacionService->asignarHorariosAJornada($jornada, [
            'horarios' => ['09:00', '11:00'],
            'reemplazar_existentes' => true,
        ]);

        $this->assertSame(1, $resumen['asignados']);
        $this->assertSame(1, $resumen['sin_asignar']);

        $p1->refresh();
        $p2->refresh();

        $this->assertSame($cancha->id, $p1->cancha_id);
        $this->assertSame('09:00:00', $p1->hora_partido);
        $this->assertNull($p2->cancha_id, 'El partido no reasignado debe limpiar su cancha previa para evitar empalmes residuales.');
        $this->assertNull($p2->hora_partido, 'El partido no reasignado debe limpiar su hora previa.');
    }

    public function test_rechaza_horarios_malformados_y_arreglos_en_campos_de_texto_sin_error_500(): void
    {
        $this->crearEquipo('Club X');
        $this->crearEquipo('Club Y');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-10-24',
            'fecha_fin' => '2026-10-24',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        // Horas fuera de rango (25:00) o basura en horarios_texto -> 422 en español en ambos formularios
        $this->actingAs($this->programador)
            ->postJson(route('jornadas.asignar-horarios', $jornada), [
                'horarios_texto' => '09:00, 25:00, abc',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios_texto']);

        $this->actingAs($this->programador)
            ->postJson(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => '2026-10-24',
                'intervalo_dias' => 7,
                'dias_duracion' => 1,
                'vueltas' => 1,
                'asignar_horarios' => 1,
                'horarios_texto' => '99:99',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios_texto']);

        // Arreglo malicioso en campos de texto (fecha_inicio[], horarios_texto[]) -> redirige y vuelve a renderizar la vista con old() sin error 500
        $this->actingAs($this->programador)
            ->from(route('grupos.generar-rol', $this->grupo))
            ->followingRedirects()
            ->post(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => ['2026-10-24'],
                'intervalo_dias' => 7,
                'dias_duracion' => 1,
                'vueltas' => 1,
                'asignar_horarios' => 1,
                'horarios_texto' => ['09:00'],
            ])
            ->assertOk();
    }

    public function test_rnf08_multiples_temporadas_historicas_no_generan_consultas_n_mas_uno(): void
    {
        $equipoIds = $this->crearEquiposEnLote($this->grupo, 10);
        $cancha = Cancha::factory()->create(['estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 5]);

        // Crear temporadas históricas cerradas con partidos previos para validar acotación por ventana (RNF-08)
        for ($t = 1; $t <= 4; $t++) {
            $tempHist = Temporada::factory()->create(['nombre' => "Histórica {$t}", 'estado' => 'cerrada']);
            $catHist = Categoria::factory()->create(['temporada_id' => $tempHist->id]);
            $grupoHist = Grupo::factory()->create(['categoria_id' => $catHist->id, 'temporada_id' => $tempHist->id]);
            $eqHist = $this->crearEquiposEnLote($grupoHist, 4);
            $jorHist = Jornada::create([
                'grupo_id' => $grupoHist->id,
                'numero_jornada' => 1,
                'fecha_inicio' => "202{$t}-05-01",
                'fecha_fin' => "202{$t}-05-02",
                'estado' => Jornada::ESTADO_PUBLICADA,
            ]);
            Partido::create([
                'jornada_id' => $jorHist->id,
                'equipo_local_id' => $eqHist[0],
                'equipo_visitante_id' => $eqHist[1],
                'cancha_id' => $cancha->id,
                'fecha_partido' => "202{$t}-05-01",
                'hora_partido' => '09:00:00',
                'estado' => Partido::ESTADO_FINALIZADO,
            ]);
        }

        $jornadaActual = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-14',
            'fecha_fin' => '2026-11-15',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        for ($i = 0; $i < 5; $i++) {
            Partido::create([
                'jornada_id' => $jornadaActual->id,
                'equipo_local_id' => $equipoIds[$i * 2],
                'equipo_visitante_id' => $equipoIds[($i * 2) + 1],
                'estado' => Partido::ESTADO_BORRADOR,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $resumen = $this->programacionService->asignarHorariosAJornada($jornadaActual, [
            'horarios' => ['08:00', '10:00', '12:00', '14:00', '16:00'],
        ]);
        $consultasEjecutadas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(5, $resumen['asignados']);
        // Precarga constante + actualizaciones + auditoría acotada, sin lecturas N+1 dentro del ciclo de evaluación
        $this->assertLessThanOrEqual(25, $consultasEjecutadas, "Se ejecutaron {$consultasEjecutadas} consultas SQL; no debe existir N+1.");
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $user = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('password123'),
            'activo' => true,
        ]);
        $user->assignRole($rol);

        return $user;
    }

    private function crearEquipo(string $nombre, ?Grupo $grupoDestino = null): Equipo
    {
        $this->secuenciaClub++;
        $grupo = $grupoDestino ?? $this->grupo;
        $club = Club::create(['nombre' => "Club {$nombre} {$this->secuenciaClub}"]);

        return Equipo::create([
            'club_id' => $club->id,
            'grupo_id' => $grupo->id,
            'temporada_id' => $grupo->temporada_id,
            'nombre' => $nombre,
        ]);
    }

    /**
     * Inserta equipos en lote para pruebas de volumen RNF-07/RNF-08.
     *
     * @return array<int, int>
     */
    private function crearEquiposEnLote(Grupo $grupo, int $cantidad, ?int $clubId = null): array
    {
        $club = $clubId ?? Club::create(['nombre' => 'Club Lote '.$grupo->id])->id;
        $ahora = now();
        $filas = [];

        for ($i = 1; $i <= $cantidad; $i++) {
            $filas[] = [
                'club_id' => $club,
                'grupo_id' => $grupo->id,
                'temporada_id' => $grupo->temporada_id,
                'nombre' => "Equipo Lote {$grupo->id}-{$i}",
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::table('equipos')->insert($filas);

        return Equipo::query()
            ->where('grupo_id', $grupo->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
