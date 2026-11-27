<?php

namespace Tests\Feature;

use App\Jobs\ProgramarCalendarioMasivoJob;
use App\Models\Arbitro;
use App\Models\AuditoriaLog;
use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\PreferenciaEquipo;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AsignacionArbitroService;
use App\Services\GeneradorEnfrentamientosService;
use App\Services\ProgramacionAutomaticaService;
use App\Services\ProgramacionJornadaService;
use App\Services\PublicacionProgramacionService;
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

    private static ?string $passwordHashCache = null;

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

    public function test_conflicto_arbitro_ocupado_no_produce_error_500_y_reporta_regla_origen(): void
    {
        $e1 = $this->crearEquipo('Águilas');
        $e2 = $this->crearEquipo('Halcones');
        $e3 = $this->crearEquipo('Cuervos');
        $e4 = $this->crearEquipo('Búhos');

        $canchaA = Cancha::factory()->create(['nombre' => 'Cancha A', 'estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);
        $canchaB = Cancha::factory()->create(['nombre' => 'Cancha B', 'estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);

        $personaArbitro = Persona::factory()->create();
        $arbitro = Arbitro::create([
            'persona_id' => $personaArbitro->id,
            'estado' => 'Activo',
        ]);

        // Dos partidos simultáneos en el único horario disponible (09:00) que comparten el mismo árbitro preasignado
        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-08',
            'fecha_fin' => '2026-08-08',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partido1 = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'arbitro_id' => $arbitro->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $partido2 = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e3->id,
            'equipo_visitante_id' => $e4->id,
            'arbitro_id' => $arbitro->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $resumen = $this->actingAs($this->programador)
            ->postJson(route('jornadas.asignar-horarios', $jornada), [
                'horarios' => ['09:00'],
                'cancha_ids' => [$canchaA->id, $canchaB->id],
            ])
            ->assertOk()
            ->json();

        // El primero se asigna a 09:00 y el segundo NO lanza SQL 500 Duplicate entry: reporta ARBITRO_OCUPADO (HU-026)
        $this->assertSame(1, $resumen['asignados']);
        $this->assertSame(1, $resumen['sin_asignar']);
        $this->assertCount(1, $resumen['conflictos']);
        $this->assertSame('ARBITRO_OCUPADO', $resumen['conflictos'][0]['codigo_regla']);
        $this->assertStringContainsString('HU-026', $resumen['conflictos'][0]['regla_origen']);

        $partido1->refresh();
        $partido2->refresh();
        $this->assertNotNull($partido1->cancha_id);
        $this->assertSame('09:00:00', $partido1->hora_partido);
        $this->assertNull($partido2->cancha_id);
        $this->assertNull($partido2->hora_partido);

        // Además, si dos partidos ya programados en fechas solapadas comparten árbitro, el detector lo reporta como bloqueante
        $partido2->update([
            'cancha_id' => $canchaB->id,
            'fecha_partido' => '2026-08-08',
            'hora_partido' => '10:00:00',
        ]);

        $conflictosDetector = app(PublicacionProgramacionService::class)->detectarConflictosDetallados($jornada->fresh());
        $codigos = array_column($conflictosDetector, 'codigo_regla');
        $this->assertContains('ARBITRO_OCUPADO', $codigos);
    }

    public function test_bloquea_modificacion_en_jornada_en_revision_y_ordena_locks_for_update(): void
    {
        $e1 = $this->crearEquipo('Lock Local');
        $e2 = $this->crearEquipo('Lock Visita');
        $cancha = Cancha::factory()->create(['estado' => 'Disponible', 'capacidad_partidos_consecutivos' => 4]);
        $personaArbitro = Persona::factory()->create();
        $arbitro = Arbitro::create([
            'persona_id' => $personaArbitro->id,
            'estado' => 'Activo',
        ]);

        $jornadaRevision = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-15',
            'fecha_fin' => '2026-08-15',
            'estado' => Jornada::ESTADO_EN_REVISION,
        ]);

        $partidoRevision = Partido::create([
            'jornada_id' => $jornadaRevision->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $this->actingAs($this->programador)
            ->postJson(route('jornadas.asignar-horarios', $jornadaRevision), [
                'horarios' => ['09:00'],
                'cancha_ids' => [$cancha->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jornada']);

        // En una jornada en borrador validamos el orden estricto de bloqueos FOR UPDATE: jornadas -> partidos -> canchas -> arbitros
        $jornadaBorrador = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 2,
            'fecha_inicio' => '2026-08-22',
            'fecha_fin' => '2026-08-22',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partidoBorrador = Partido::create([
            'jornada_id' => $jornadaBorrador->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'arbitro_id' => $arbitro->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $tablasBloqueadas = [];
        DB::listen(static function ($query) use (&$tablasBloqueadas): void {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'for update')) {
                return;
            }
            foreach (['jornadas', 'partidos', 'canchas', 'arbitros'] as $tabla) {
                if (str_contains($sql, "from `{$tabla}`") || str_contains($sql, "from {$tabla}")) {
                    $tablasBloqueadas[] = $tabla;
                }
            }
        });

        // Ejecutar asignación por grupo ignora silenciosamente la jornada en revisión y procesa solo el borrador
        $resumenGrupo = $this->programacionService->asignarHorariosAGrupo($this->grupo, [
            'horarios' => ['09:00'],
            'cancha_ids' => [$cancha->id],
        ]);

        $this->assertSame(1, $resumenGrupo['asignados']);
        $this->assertNull($partidoRevision->fresh()->hora_partido);
        $this->assertSame('09:00:00', $partidoBorrador->fresh()->hora_partido);
        $this->assertSame(['jornadas', 'partidos', 'canchas', 'arbitros'], $tablasBloqueadas);
    }

    public function test_medianoche_detecta_y_evita_empalme_cruzando_las_00_00(): void
    {
        $e1 = $this->crearEquipo('Nocturno A');
        $e2 = $this->crearEquipo('Nocturno B');
        $e3 = $this->crearEquipo('Madrugada C');
        $e4 = $this->crearEquipo('Madrugada D');

        $cancha = Cancha::factory()->create([
            'nombre' => 'Cancha Nocturna',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 5,
        ]);

        // Partido previo el sábado 2026-08-08 a las 23:00 (cierra hasta el domingo 2026-08-09 01:00)
        $jornadaSabado = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-08-08',
            'fecha_fin' => '2026-08-08',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        Partido::create([
            'jornada_id' => $jornadaSabado->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'cancha_id' => $cancha->id,
            'fecha_partido' => '2026-08-08',
            'hora_partido' => '23:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Jornada del domingo 2026-08-09: el horario 00:30 se traslapa con las 23:00 del sábado y debe evitarse
        $jornadaDomingo = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 2,
            'fecha_inicio' => '2026-08-09',
            'fecha_fin' => '2026-08-09',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partidoMadrugada = Partido::create([
            'jornada_id' => $jornadaDomingo->id,
            'equipo_local_id' => $e3->id,
            'equipo_visitante_id' => $e4->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $resumenSoloTraslape = $this->programacionService->asignarHorariosAJornada($jornadaDomingo, [
            'horarios' => ['00:30'],
            'cancha_ids' => [$cancha->id],
        ]);

        $this->assertSame(0, $resumenSoloTraslape['asignados']);
        $this->assertSame('CANCHA_OCUPADA', $resumenSoloTraslape['conflictos'][0]['codigo_regla']);
        $this->assertNull($partidoMadrugada->fresh()->hora_partido);

        // Con 01:00 disponible, debe saltar 00:30 y asignar 01:00 sin empalmar
        $resumenConAlternativa = $this->programacionService->asignarHorariosAJornada($jornadaDomingo, [
            'horarios' => ['00:30', '01:00'],
            'cancha_ids' => [$cancha->id],
        ]);
        $this->assertSame(1, $resumenConAlternativa['asignados']);
        $this->assertSame('01:00:00', $partidoMadrugada->fresh()->hora_partido);

        // Si manualmente existiera a las 00:30, el detector de conflictos debe denunciar el empalme de medianoche
        $partidoMadrugada->update([
            'cancha_id' => $cancha->id,
            'fecha_partido' => '2026-08-09',
            'hora_partido' => '00:30:00',
        ]);

        $conflictos = app(PublicacionProgramacionService::class)->detectarConflictosDetallados($jornadaDomingo->fresh());
        $this->assertContains('CANCHA_OCUPADA', array_column($conflictos, 'codigo_regla'));
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
        $partidos = Partido::query()
            ->with('jornada')
            ->whereIn('jornada_id', $this->grupo->jornadas()->pluck('id'))
            ->get();
        $this->assertCount(6, $partidos);
        $this->assertSame(6, $partidos->whereNotNull('cancha_id')->whereNotNull('hora_partido')->count());

        // Cada partido confirmado conserva exactamente cancha, fecha y hora mostrados en previsualización
        $esperadosPorClave = [];
        foreach ($preview['jornadas'] as $jornadaPreview) {
            foreach ($jornadaPreview['partidos'] as $partidoPreview) {
                $clave = $jornadaPreview['numero_jornada'].'|'.$partidoPreview['equipo_local_id'].'|'.$partidoPreview['equipo_visitante_id'];
                $esperadosPorClave[$clave] = [
                    'cancha_id' => $partidoPreview['cancha_id'],
                    'fecha' => $partidoPreview['fecha_partido'],
                    'hora' => $partidoPreview['hora_partido'].':00',
                ];
            }
        }

        foreach ($partidos as $partidoPersistido) {
            $clave = $partidoPersistido->jornada->numero_jornada.'|'.$partidoPersistido->equipo_local_id.'|'.$partidoPersistido->equipo_visitante_id;
            $this->assertArrayHasKey($clave, $esperadosPorClave);
            $this->assertSame($esperadosPorClave[$clave]['cancha_id'], $partidoPersistido->cancha_id);
            $this->assertSame($esperadosPorClave[$clave]['fecha'], $partidoPersistido->fecha_partido?->toDateString());
            $this->assertSame($esperadosPorClave[$clave]['hora'], $partidoPersistido->hora_partido);
        }
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

        // Verificar que la ejecución en segundo plano sin sesión HTTP activa conserva el usuario solicitante en auditoría (RNF-30)
        auth()->logout();
        $inicio = microtime(true);

        (new ProgramarCalendarioMasivoJob($this->temporada->id, [
            'horarios' => ['08:00', '10:00', '12:00', '14:00', '16:00'],
            'cancha_ids' => array_map(fn (Cancha $c): int => $c->id, $canchas),
        ], $this->programador->id))->handle($this->programacionService);

        $duracionSegundos = microtime(true) - $inicio;

        $this->assertLessThan(60.0, $duracionSegundos, "La programación masiva de 400 partidos tardó {$duracionSegundos}s y excede RNF-07 (60s).");

        $resumenCache = Cache::get("programacion_masiva_temporada_{$this->temporada->id}");
        $this->assertIsArray($resumenCache);
        $this->assertSame('completado', $resumenCache['estado']);
        $this->assertSame(400, $resumenCache['total_partidos']);
        $this->assertSame(400, $resumenCache['asignados']);
        $this->assertTrue($resumenCache['completado']);

        $this->assertTrue(
            AuditoriaLog::query()
                ->where('tabla_afectada', 'partidos')
                ->where('usuario_id', $this->programador->id)
                ->where('motivo', 'like', '%Programación automática masiva%')
                ->exists(),
            'Los registros de auditoría del job en segundo plano deben conservar el usuario_id solicitante.'
        );

        // Verificar que no exista ningún empalme de cancha en los 400 partidos persistidos
        $slotsOcupados = Partido::query()
            ->whereIn('jornada_id', Jornada::query()->whereHas('grupo', fn ($q) => $q->where('temporada_id', $this->temporada->id))->pluck('id'))
            ->get(['cancha_id', 'fecha_partido', 'hora_partido'])
            ->map(fn (Partido $p): string => $p->cancha_id.'|'.$p->fecha_partido?->toDateString().'|'.$p->hora_partido);

        $this->assertCount(400, $slotsOcupados->unique());

        // La vista de detalle de la temporada debe leer y mostrar el resumen guardado en caché (HU-033 CA1)
        $this->actingAs($this->programador)
            ->get(route('temporadas.show', $this->temporada))
            ->assertOk()
            ->assertSee('Último resumen de programación masiva en segundo plano')
            ->assertSee('Partidos procesados:')
            ->assertSee('400');

        // Encolado HTTP en segundo plano (en_segundo_plano = 1) pasa el id del usuario solicitante al Job
        Queue::fake();
        $this->actingAs($this->programador)
            ->postJson(route('temporadas.asignar-horarios-masivo', $this->temporada), [
                'en_segundo_plano' => true,
            ])
            ->assertStatus(202)
            ->assertJson(['enqueued' => true, 'temporada_id' => $this->temporada->id]);

        Queue::assertPushed(ProgramarCalendarioMasivoJob::class, function (ProgramarCalendarioMasivoJob $job): bool {
            return $job->temporadaId === $this->temporada->id
                && $job->usuarioId === $this->programador->id;
        });

        $enProceso = Cache::get("programacion_masiva_temporada_{$this->temporada->id}");
        $this->assertSame('en_proceso', $enProceso['estado'] ?? null);
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

        // Todos los roles sin privilegios de programación -> 403 con mensaje en español
        $rolesSinPermiso = [
            'Tesorero',
            'Presidente',
            'Capturista',
            'Tribunal de Penas',
            'Comisión Arbitral',
            'Árbitro en Campo',
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesSinPermiso as $indice => $rol) {
            $usuario = $this->crearUsuario("sin_permiso_{$indice}@ligatest.com", $rol);

            $this->actingAs($usuario)
                ->postJson(route('jornadas.asignar-horarios', $jornada))
                ->assertForbidden()
                ->assertJsonFragment(['mensaje' => 'No cuenta con privilegios para modificar jornadas.']);

            $this->actingAs($usuario)
                ->postJson(route('grupos.asignar-horarios', $this->grupo))
                ->assertForbidden()
                ->assertJsonFragment(['mensaje' => 'No cuenta con privilegios para crear jornadas.']);

            $this->actingAs($usuario)
                ->postJson(route('temporadas.asignar-horarios-masivo', $this->temporada))
                ->assertForbidden()
                ->assertJsonFragment(['mensaje' => 'No cuenta con privilegios para ejecutar la programación automática masiva.']);
        }

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

    public function test_mensajes_de_validacion_422_estan_en_espanol_para_todos_los_parametros(): void
    {
        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-10-10',
            'fecha_fin' => '2026-10-11',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        // Errores de tipo, límites, arreglos y enteros deben responder en español sin cadenas genéricas en inglés
        $respuestaJornada = $this->actingAs($this->programador)
            ->postJson(route('jornadas.asignar-horarios', $jornada), [
                'cancha_ids' => 'no-es-arreglo',
                'horarios' => array_fill(0, 15, '09:00'),
                'reemplazar_existentes' => 'si-claro',
                'en_segundo_plano' => 'invalido',
            ])
            ->assertUnprocessable();

        $erroresJornada = json_encode($respuestaJornada->json('errors'), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('must be', (string) $erroresJornada);
        $this->assertStringNotContainsString('field is', (string) $erroresJornada);
        $respuestaJornada
            ->assertJsonValidationErrors(['cancha_ids', 'horarios', 'reemplazar_existentes', 'en_segundo_plano']);

        // Elementos internos inválidos en cancha_ids y número de jornada inválido en previsualizar-rol
        $respuestaCanchaItem = $this->actingAs($this->programador)
            ->postJson(route('jornadas.asignar-horarios', $jornada), [
                'cancha_ids' => ['abc'],
                'horarios' => '09:00',
            ])
            ->assertUnprocessable();

        $erroresItem = json_encode($respuestaCanchaItem->json('errors'), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('must be', (string) $erroresItem);
        $this->assertStringNotContainsString('field is', (string) $erroresItem);
        $respuestaCanchaItem->assertJsonValidationErrors(['cancha_ids.0', 'horarios']);

        $respuestaPrevisualizar = $this->actingAs($this->programador)
            ->postJson(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => 'no-fecha',
                'intervalo_dias' => 0,
                'dias_duracion' => 45,
                'vueltas' => 5,
            ])
            ->assertUnprocessable();

        $erroresPrevisualizar = json_encode($respuestaPrevisualizar->json('errors'), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('must be', (string) $erroresPrevisualizar);
        $this->assertStringNotContainsString('field is', (string) $erroresPrevisualizar);
        $respuestaPrevisualizar->assertJsonValidationErrors(['fecha_inicio', 'intervalo_dias', 'dias_duracion', 'vueltas']);
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

    public function test_json_alterado_al_confirmar_rol_descarta_fechas_fuera_de_rango_sin_error_500(): void
    {
        $this->crearEquipo('Tamper 1');
        $this->crearEquipo('Tamper 2');
        $this->crearEquipo('Tamper 3');
        $this->crearEquipo('Tamper 4');

        $cancha = Cancha::factory()->create(['nombre' => 'Cancha Tamper', 'estado' => 'Disponible']);

        $preview = $this->actingAs($this->programador)
            ->postJson(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => '2027-05-01',
                'intervalo_dias' => 7,
                'dias_duracion' => 1,
                'vueltas' => 1,
                'asignar_horarios' => true,
                'horarios' => ['09:00', '11:00'],
                'cancha_ids' => [$cancha->id],
            ])
            ->assertOk()
            ->json();

        // Alterar payload oculto con fecha fuera de temporada, fecha inexistente, hora no permitida y arreglo malicioso
        $preview['jornadas'][0]['partidos'][0]['fecha_partido'] = '2030-01-01';
        $preview['jornadas'][0]['partidos'][0]['hora_partido'] = '03:17';
        $preview['jornadas'][0]['partidos'][1]['fecha_partido'] = '2026-02-30';
        $preview['jornadas'][1]['partidos'][0]['fecha_partido'] = ['x'];

        $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($preview, JSON_THROW_ON_ERROR),
                'asignar_horarios' => 1,
                'horarios' => ['09:00', '11:00'],
                'cancha_ids' => [$cancha->id],
            ])
            ->assertRedirect(route('grupos.jornadas.index', $this->grupo))
            ->assertSessionHas('success');

        $jornadas = $this->grupo->jornadas()->with('partidos')->get();
        foreach ($jornadas as $jornada) {
            $inicioJ = $jornada->fecha_inicio->toDateString();
            $finJ = $jornada->fecha_fin->toDateString();
            foreach ($jornada->partidos as $partido) {
                $fechaGuardada = $partido->fecha_partido?->toDateString();
                $this->assertNotNull($fechaGuardada);
                $this->assertGreaterThanOrEqual($inicioJ, $fechaGuardada);
                $this->assertLessThanOrEqual($finJ, $fechaGuardada);
                $this->assertContains($partido->hora_partido, ['09:00:00', '11:00:00']);
            }
        }
    }

    public function test_medianoche_hacia_adelante_evita_y_detecta_empalme_en_cancha_y_arbitro(): void
    {
        $e1 = $this->crearEquipo('Aurora FC');
        $e2 = $this->crearEquipo('Bruma FC');
        $e3 = $this->crearEquipo('Noche FC');
        $e4 = $this->crearEquipo('Velada FC');

        $cancha = Cancha::factory()->create(['nombre' => 'Cancha C4', 'estado' => 'Disponible']);
        $canchaAlterna = Cancha::factory()->create(['nombre' => 'Cancha Alterna', 'estado' => 'Disponible']);

        $personaArbitro = Persona::factory()->create();
        $arbitro = Arbitro::create(['persona_id' => $personaArbitro->id, 'estado' => 'Activo']);

        // Partido existente al día siguiente a las 00:30 (2026-10-21)
        $jornadaSiguiente = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 2,
            'fecha_inicio' => '2026-10-21',
            'fecha_fin' => '2026-10-21',
            'estado' => Jornada::ESTADO_PUBLICADA,
        ]);

        Partido::create([
            'jornada_id' => $jornadaSiguiente->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'cancha_id' => $cancha->id,
            'arbitro_id' => $arbitro->id,
            'fecha_partido' => '2026-10-21',
            'hora_partido' => '00:30:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        // Jornada en borrador el 2026-10-20 con partido que intenta programar a las 23:00 en Cancha C4 (terminaría 01:00 del 21)
        $jornadaPrevia = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-10-20',
            'fecha_fin' => '2026-10-20',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partidoPrevio = Partido::create([
            'jornada_id' => $jornadaPrevia->id,
            'equipo_local_id' => $e3->id,
            'equipo_visitante_id' => $e4->id,
            'arbitro_id' => $arbitro->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Solo 23:00 sobre Cancha C4: debe evitarlo por traslape con 00:30 del día siguiente (cancha y árbitro)
        $resCancha = $this->programacionService->asignarHorariosAJornada($jornadaPrevia, [
            'horarios' => ['23:00'],
            'cancha_ids' => [$cancha->id],
        ]);
        $this->assertSame(0, $resCancha['asignados']);
        $this->assertNull($partidoPrevio->fresh()->hora_partido);

        // Incluso en otra cancha, a las 23:30 el árbitro choca con 00:30 del día 21
        $resArbitro = $this->programacionService->asignarHorariosAJornada($jornadaPrevia, [
            'horarios' => ['23:30'],
            'cancha_ids' => [$canchaAlterna->id],
        ]);
        $this->assertSame(0, $resArbitro['asignados']);
        $this->assertSame('ARBITRO_OCUPADO', $resArbitro['conflictos'][0]['codigo_regla']);

        // Si el partido previo existiera en C4 el 20/10 a las 23:00, el detector debe denunciar CANCHA_OCUPADA hacia adelante
        $partidoPrevio->update([
            'cancha_id' => $cancha->id,
            'fecha_partido' => '2026-10-20',
            'hora_partido' => '23:00:00',
        ]);
        $conflictosForward = app(PublicacionProgramacionService::class)->detectarConflictosDetallados($jornadaPrevia->fresh());
        $this->assertContains('CANCHA_OCUPADA', array_column($conflictosForward, 'codigo_regla'));
        $this->assertContains('ARBITRO_OCUPADO', array_column($conflictosForward, 'codigo_regla'));
    }

    public function test_regla_unica_arbitro_ventana_120_min_en_hu026_hu027_motor_y_detector(): void
    {
        $e1 = $this->crearEquipo('Arbitro E1');
        $e2 = $this->crearEquipo('Arbitro E2');
        $e3 = $this->crearEquipo('Arbitro E3');
        $e4 = $this->crearEquipo('Arbitro E4');

        $cancha1 = Cancha::factory()->create(['estado' => 'Disponible']);
        $cancha2 = Cancha::factory()->create(['estado' => 'Disponible']);
        $cancha3 = Cancha::factory()->create(['estado' => 'Disponible']);

        $rolArbitro = app(\App\Services\IdentidadPersonaService::class)->asignarRol([
            'nombre' => 'Juez',
            'apellidos' => 'Ventana Central',
            'fecha_nacimiento' => '1986-04-10',
            'curp' => 'ARBV860410HDFRRN09',
        ], 'arbitro', $this->temporada);
        $arbitro = Arbitro::create(['persona_id' => $rolArbitro->persona_id, 'estado' => 'Activo']);

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-12-12',
            'fecha_fin' => '2026-12-12',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'cancha_id' => $cancha1->id,
            'arbitro_id' => $arbitro->id,
            'fecha_partido' => '2026-12-12',
            'hora_partido' => '09:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $partidoEmpalme = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e3->id,
            'equipo_visitante_id' => $e4->id,
            'cancha_id' => $cancha2->id,
            'fecha_partido' => '2026-12-12',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        // HU-026: AsignacionArbitroService rechaza 10:00 por ventana de 120 min contra 09:00
        try {
            app(AsignacionArbitroService::class)->asignar($partidoEmpalme, $arbitro);
            $this->fail('Se esperaba ValidationException por traslape de 120 min del árbitro en HU-026.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('arbitro_id', $e->errors());
        }

        // HU-027: ProgramacionJornadaService::actualizarPartido aplica la misma regla única
        try {
            app(ProgramacionJornadaService::class)->actualizarPartido($partidoEmpalme, [
                'arbitro_id' => $arbitro->id,
            ]);
            $this->fail('Se esperaba ValidationException por traslape de 120 min del árbitro en HU-027.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('arbitro_id', $e->errors());
        }

        // A las 11:00 (justo al cumplir 120 min) HU-026 sí permite asignar al mismo árbitro
        $partidoValido = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $this->crearEquipo('Arbitro E5')->id,
            'equipo_visitante_id' => $this->crearEquipo('Arbitro E6')->id,
            'cancha_id' => $cancha3->id,
            'fecha_partido' => '2026-12-12',
            'hora_partido' => '11:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        app(AsignacionArbitroService::class)->asignar($partidoValido, $arbitro);
        $this->assertSame($arbitro->id, $partidoValido->fresh()->arbitro_id);
        $this->assertSame([], app(PublicacionProgramacionService::class)->detectarConflictos($jornada->fresh()));
    }

    public function test_hu027_sancion_equipo_por_3_defaults_impide_programacion_automatica_y_publicacion(): void
    {
        $suspendido = $this->crearEquipo('Suspendido Default');
        $suspendido->update(['partidos_default_acumulados' => 3]);
        $rival = $this->crearEquipo('Rival Limpio');
        $limpioA = $this->crearEquipo('Limpio A');
        $limpioB = $this->crearEquipo('Limpio B');

        $cancha = Cancha::factory()->create(['estado' => 'Disponible']);
        $this->assertSame(4, $cancha->capacidad_partidos_consecutivos);

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-10-17',
            'fecha_fin' => '2026-10-17',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $partidoSancionado = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $suspendido->id,
            'equipo_visitante_id' => $rival->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);
        $partidoLimpio = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $limpioA->id,
            'equipo_visitante_id' => $limpioB->id,
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $resumen = $this->programacionService->asignarHorariosAJornada($jornada);
        $this->assertSame(1, $resumen['asignados']);
        $this->assertSame(1, $resumen['sin_asignar']);
        $this->assertSame('SANCION_EQUIPO_VIGENTE', $resumen['conflictos'][0]['codigo_regla']);
        $this->assertNull($partidoSancionado->fresh()->hora_partido);
        $this->assertNotNull($partidoLimpio->fresh()->hora_partido);

        $conflictos = app(PublicacionProgramacionService::class)->detectarConflictosDetallados($jornada->fresh());
        $this->assertContains('SANCION_EQUIPO_VIGENTE', array_column($conflictos, 'codigo_regla'));
    }

    public function test_programador_puede_registrar_preferencias_de_equipo_y_motor_usa_pesos_configurables(): void
    {
        $eq1 = $this->crearEquipo('Pref Local');
        $eq2 = $this->crearEquipo('Pref Visita');
        $cancha1 = Cancha::factory()->create(['nombre' => 'Cancha Uno', 'estado' => 'Disponible']);
        $cancha2 = Cancha::factory()->create(['nombre' => 'Cancha Dos', 'estado' => 'Disponible']);

        // Un delegado o jugador no puede acceder todavía en este sprint (403)
        $delegado = $this->crearUsuario('delegado_pref@liga.com', 'Delegado del Club');
        $this->actingAs($delegado)
            ->get(route('grupos.preferencias.edit', $this->grupo))
            ->assertForbidden();

        // El Programador de Partidos registra las preferencias solicitadas por los clubes
        $this->actingAs($this->programador)
            ->get(route('grupos.preferencias.edit', $this->grupo))
            ->assertOk()
            ->assertSee('Pref Local');

        $this->actingAs($this->programador)
            ->put(route('grupos.preferencias.update', $this->grupo), [
                'preferencias' => [
                    $eq1->id => [
                        'cancha_id_preferida' => $cancha2->id,
                        'dia_preferido' => 'Domingo',
                        'hora_preferida' => '15:00',
                    ],
                ],
            ])
            ->assertRedirect(route('grupos.preferencias.edit', $this->grupo))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('preferencias_equipo', [
            'equipo_id' => $eq1->id,
            'cancha_id_preferida' => $cancha2->id,
            'dia_preferido' => 'Domingo',
            'hora_preferida' => '15:00:00',
        ]);

        // Pesos configurables en config/programacion.php
        $this->assertSame(40, config('programacion.pesos_preferencias.local.cancha'));
        $this->assertSame(15, config('programacion.pesos_preferencias.visitante.cancha'));

        // Validación en español
        $this->actingAs($this->programador)
            ->putJson(route('grupos.preferencias.update', $this->grupo), [
                'preferencias' => [
                    $eq2->id => [
                        'dia_preferido' => 'Feriado',
                        'hora_preferida' => '25:99',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preferencias.'.$eq2->id.'.dia_preferido', 'preferencias.'.$eq2->id.'.hora_preferida']);
    }

    public function test_job_masivo_marca_cancelado_si_temporada_cierra_y_oculta_resumen_a_jugador(): void
    {
        Cache::put(
            "programacion_masiva_temporada_{$this->temporada->id}",
            ['estado' => 'en_proceso'],
            now()->addHours(6)
        );

        $this->temporada->update(['estado' => 'cerrada']);
        (new ProgramarCalendarioMasivoJob($this->temporada->id, [], $this->programador->id))->handle($this->programacionService);

        $estado = Cache::get("programacion_masiva_temporada_{$this->temporada->id}");
        $this->assertIsArray($estado);
        $this->assertSame('cancelado', $estado['estado']);

        // Un jugador con temporadas.ver NO debe ver el resumen ni los conflictos internos del motor
        $jugador = $this->crearUsuario('jugador_resumen@liga.com', 'Jugador');
        $this->actingAs($jugador)
            ->get(route('temporadas.show', $this->temporada))
            ->assertOk()
            ->assertDontSee('Programación masiva en segundo plano');
    }

    private function crearUsuario(string $email, string $rol): User
    {
        self::$passwordHashCache ??= Hash::make('password123');

        $user = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => self::$passwordHashCache,
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
