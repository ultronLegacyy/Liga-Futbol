<?php

namespace Tests\Feature;

use App\Models\Arbitro;
use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\CedulaArbitral;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use App\Services\GeneradorEnfrentamientosService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas unitarias y de integración para la generación automática de enfrentamientos (HU-032 / SCRUM-62).
 *
 * Cubre:
 * - SCRUM-161 (S4-T19): Algoritmo Berger / Round-Robin sin duplicados y descansos rotativos en grupos impares (RF-07.1, RF-06.2).
 * - SCRUM-162 (S4-T20): Vistas de configuración, previsualización y confirmación transaccional con auditoría.
 * - SCRUM-163 (S4-T21): Pruebas exhaustivas con grupos pares e impares, RBAC y control de temporadas cerradas.
 */
class GeneradorEnfrentamientosTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $programador;

    private User $usuarioSinPermiso;

    private Temporada $temporada;

    private Grupo $grupo;

    private GeneradorEnfrentamientosService $generadorService;

    private int $clubCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_rol@liga.com', 'Administrador General');
        $this->programador = $this->crearUsuario('programador_rol@liga.com', 'Programador de Partidos');
        $this->usuarioSinPermiso = $this->crearUsuario('tesorero_rol@liga.com', 'Tesorero');

        $this->temporada = Temporada::factory()->activa()->create([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2027-12-31',
        ]);
        $categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $this->temporada->id,
        ]);

        $this->generadorService = app(GeneradorEnfrentamientosService::class);
    }

    /**
     * SCRUM-161 / S4-T19: Algoritmo con grupo PAR (4 y 6 equipos).
     * Criterio 1: Cada equipo juega contra otro sin duplicados en la misma jornada y sin descansos.
     */
    public function test_algoritmo_berger_con_grupo_par_sin_equipos_duplicados_y_sin_descanso(): void
    {
        // Caso con 4 equipos: [E1, E2, E3, E4]
        $e1 = $this->crearEquipo('Equipo A');
        $e2 = $this->crearEquipo('Equipo B');
        $e3 = $this->crearEquipo('Equipo C');
        $e4 = $this->crearEquipo('Equipo D');

        $equiposIds = [$e1->id, $e2->id, $e3->id, $e4->id];
        $rol = $this->generadorService->calcularRolBerger($equiposIds, 1);

        // N=4 equipos -> 3 jornadas en una sola vuelta
        $this->assertCount(3, $rol);

        $parejasJugadas = [];

        foreach ($rol as $ronda) {
            // Grupo par no tiene equipo en descanso
            $this->assertNull($ronda['equipo_descanso_id']);
            // 4 equipos -> 2 partidos por jornada
            $this->assertCount(2, $ronda['partidos']);

            $equiposEnJornada = [];
            foreach ($ronda['partidos'] as $partido) {
                $local = $partido['equipo_local_id'];
                $visitante = $partido['equipo_visitante_id'];

                $this->assertNotEquals($local, $visitante);
                $this->assertFalse(in_array($local, $equiposEnJornada, true), "Equipo local {$local} duplicado en jornada {$ronda['numero_jornada']}");
                $this->assertFalse(in_array($visitante, $equiposEnJornada, true), "Equipo visitante {$visitante} duplicado en jornada {$ronda['numero_jornada']}");

                $equiposEnJornada[] = $local;
                $equiposEnJornada[] = $visitante;

                // Registrar pareja no dirigida
                $par = [$local, $visitante];
                sort($par);
                $clave = "{$par[0]}-{$par[1]}";
                $this->assertArrayNotHasKey($clave, $parejasJugadas, "El enfrentamiento {$clave} ya se había disputado.");
                $parejasJugadas[$clave] = true;
            }

            // Todos los 4 equipos deben jugar en la jornada
            $this->assertCount(4, $equiposEnJornada);
        }

        // Total combinaciones C(4, 2) = 6 enfrentamientos únicos
        $this->assertCount(6, $parejasJugadas);
    }

    /**
     * SCRUM-161 / S4-T19: Algoritmo con grupo IMPAR (3 y 5 equipos).
     * Criterio 2: Asignación equitativa y rotativa de descansos; el equipo en descanso no juega.
     */
    public function test_algoritmo_berger_con_grupo_impar_asigna_descanso_rotativo_y_equitativo(): void
    {
        // Caso con 5 equipos: [E1, E2, E3, E4, E5]
        $equipos = [];
        for ($i = 1; $i <= 5; $i++) {
            $equipos[] = $this->crearEquipo("Equipo Impar {$i}");
        }

        $equiposIds = array_map(fn ($e) => $e->id, $equipos);
        $rol = $this->generadorService->calcularRolBerger($equiposIds, 1);

        // N=5 equipos -> 5 jornadas por vuelta
        $this->assertCount(5, $rol);

        $descansosPorEquipo = [];
        $parejasJugadas = [];

        foreach ($rol as $ronda) {
            $descanso = $ronda['equipo_descanso_id'];
            $this->assertNotNull($descanso, "La jornada {$ronda['numero_jornada']} debe tener equipo en descanso");
            $this->assertTrue(in_array($descanso, $equiposIds, true));

            // Contabilizar que cada equipo descanse equitativamente
            $descansosPorEquipo[$descanso] = ($descansosPorEquipo[$descanso] ?? 0) + 1;

            // 5 equipos -> 1 descansa, 4 juegan -> 2 partidos por jornada
            $this->assertCount(2, $ronda['partidos']);

            $equiposEnJornada = [];
            foreach ($ronda['partidos'] as $partido) {
                $local = $partido['equipo_local_id'];
                $visitante = $partido['equipo_visitante_id'];

                // El equipo en descanso NO debe participar en ningún partido de la jornada (HU-028)
                $this->assertNotEquals($descanso, $local, "El equipo en descanso no puede jugar de local en jornada {$ronda['numero_jornada']}");
                $this->assertNotEquals($descanso, $visitante, "El equipo en descanso no puede jugar de visitante en jornada {$ronda['numero_jornada']}");

                $this->assertFalse(in_array($local, $equiposEnJornada, true), "Equipo local duplicado");
                $this->assertFalse(in_array($visitante, $equiposEnJornada, true), "Equipo visitante duplicado");

                $equiposEnJornada[] = $local;
                $equiposEnJornada[] = $visitante;

                $par = [$local, $visitante];
                sort($par);
                $clave = "{$par[0]}-{$par[1]}";
                $this->assertArrayNotHasKey($clave, $parejasJugadas);
                $parejasJugadas[$clave] = true;
            }

            // Los 4 equipos activos deben ser distintos entre sí y del que descansa
            $this->assertCount(4, $equiposEnJornada);
            $this->assertFalse(in_array($descanso, $equiposEnJornada, true));
        }

        // Criterio 2: Cada uno de los 5 equipos descansó exactamente 1 vez
        $this->assertCount(5, $descansosPorEquipo);
        foreach ($descansosPorEquipo as $equipoId => $veces) {
            $this->assertEquals(1, $veces, "El equipo {$equipoId} debió descansar exactamente 1 vez");
        }

        // C(5, 2) = 10 partidos en total
        $this->assertCount(10, $parejasJugadas);
    }

    /**
     * SCRUM-161: Verificación de 2 vueltas (ida y vuelta) con inversión exacta de localías.
     */
    public function test_algoritmo_berger_dos_vueltas_invierte_localias_adecuadamente(): void
    {
        $e1 = $this->crearEquipo('Localista A');
        $e2 = $this->crearEquipo('Localista B');
        $e3 = $this->crearEquipo('Localista C');
        $e4 = $this->crearEquipo('Localista D');

        $equiposIds = [$e1->id, $e2->id, $e3->id, $e4->id];
        $rol = $this->generadorService->calcularRolBerger($equiposIds, 2);

        // 4 equipos -> 3 jornadas vuelta 1 + 3 jornadas vuelta 2 = 6 jornadas
        $this->assertCount(6, $rol);

        for ($j = 0; $j < 3; $j++) {
            $primera = $rol[$j]['partidos'];
            $segunda = $rol[$j + 3]['partidos'];

            $this->assertCount(count($primera), $segunda);

            // Verificar que cada partido de la primera vuelta esté invertido en la segunda
            foreach ($primera as $partido1) {
                $encontradoInvertido = false;
                foreach ($segunda as $partido2) {
                    if ($partido2['equipo_local_id'] === $partido1['equipo_visitante_id']
                        && $partido2['equipo_visitante_id'] === $partido1['equipo_local_id']) {
                        $encontradoInvertido = true;
                        break;
                    }
                }
                $this->assertTrue($encontradoInvertido, 'No se encontró el partido de revancha con localía invertida.');
            }
        }
    }

    /**
     * SCRUM-162 / S4-T20: Vista de configuración del rol.
     */
    public function test_pantalla_configurar_muestra_informacion_del_grupo_y_equipos(): void
    {
        $this->crearEquipo('Equipo Beta 1');
        $this->crearEquipo('Equipo Beta 2');

        $response = $this->actingAs($this->programador)
            ->get(route('grupos.generar-rol', $this->grupo));

        $response->assertOk()
            ->assertViewIs('jornadas.generar-rol')
            ->assertSee('Generar Rol Regular de Enfrentamientos')
            ->assertSee($this->grupo->nombre)
            ->assertSee('Round-Robin (Sistema Berger)');
    }

    /**
     * SCRUM-162 / S4-T20: Previsualización de la propuesta de enfrentamientos en Blade y JSON.
     */
    public function test_previsualizacion_retorna_propuesta_estructurada_y_vista(): void
    {
        $this->crearEquipo('Tigres');
        $this->crearEquipo('Leones');
        $this->crearEquipo('Águilas');

        // Petición HTML
        $responseHtml = $this->actingAs($this->programador)
            ->post(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => '2026-11-14',
                'intervalo_dias' => 7,
                'dias_duracion' => 2,
                'vueltas' => 1,
            ]);

        $responseHtml->assertOk()
            ->assertViewIs('jornadas.previsualizar-rol')
            ->assertSee('Propuesta de Rol Regular')
            ->assertSee('Tigres')
            ->assertSee('Leones')
            ->assertSee('Águilas');

        // Petición JSON (API)
        $responseJson = $this->actingAs($this->programador)
            ->postJson(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => '2026-11-14',
                'intervalo_dias' => 7,
                'dias_duracion' => 2,
                'vueltas' => 1,
            ]);

        $responseJson->assertOk()
            ->assertJsonPath('total_equipos', 3)
            ->assertJsonPath('total_jornadas', 3)
            ->assertJsonStructure([
                'grupo_id',
                'total_equipos',
                'total_jornadas',
                'vueltas',
                'jornadas' => [
                    '*' => [
                        'numero_jornada',
                        'fecha_inicio',
                        'fecha_fin',
                        'equipo_descanso_id',
                        'partidos' => [
                            '*' => [
                                'equipo_local_id',
                                'equipo_local_nombre',
                                'equipo_visitante_id',
                                'equipo_visitante_nombre',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * SCRUM-162 / S4-T20: Confirmación y persistencia transaccional del rol regular en la base de datos con auditoría.
     */
    public function test_confirmar_persiste_jornadas_y_partidos_en_base_de_datos_con_auditoria(): void
    {
        $e1 = $this->crearEquipo('Cobras');
        $e2 = $this->crearEquipo('Delfines');
        $e3 = $this->crearEquipo('Halcones');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-20',
            'intervalo_dias' => 7,
            'dias_duracion' => 2,
            'vueltas' => 1,
        ]);

        $response = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
                'sobrescribir' => '0',
            ]);

        $response->assertRedirect(route('grupos.jornadas.index', $this->grupo))
            ->assertSessionHas('success');

        // Se deben haber creado 3 jornadas en el grupo
        $this->assertDatabaseCount('jornadas', 3);
        $this->assertDatabaseHas('jornadas', [
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-20',
        ]);

        // C(3, 2) = 3 partidos generados en borrador (Obligatorio 6)
        $this->assertDatabaseCount('partidos', 3);
        $this->assertDatabaseHas('partidos', [
            'estado' => Partido::ESTADO_BORRADOR,
            'fecha_partido' => '2026-11-20',
        ]);

        // Verificar que quedó registrado en la tabla de auditoría con su motivo (RNF-30)
        $this->assertDatabaseHas('auditoria_logs', [
            'tabla_afectada' => 'jornadas',
            'accion' => 'crear',
            'usuario_id' => $this->programador->id,
            'motivo' => 'Generación automática de rol de enfrentamientos (HU-032 / RF-07.1)',
        ]);
    }

    /**
     * Obligatorio 6: El rol generado nace en borrador.
     * Al completarse con cancha y horario (sin motivo obligatorio), se promueve a programado,
     * se le asigna árbitro y el árbitro puede abrir la cédula arbitral.
     */
    public function test_rol_generado_en_borrador_se_completa_con_cancha_horario_sin_motivo_asigna_arbitro_y_abre_cedula(): void
    {
        $e1 = $this->crearEquipo('Equipo Alfa');
        $e2 = $this->crearEquipo('Equipo Beta');

        // Cancha disponible
        $cancha = Cancha::factory()->create([
            'nombre' => 'Cancha Principal Rol',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 4,
        ]);

        // Árbitro y usuario con rol Árbitro en Campo con vigencia en la temporada (HU-016)
        $rolArbitro = app(\App\Services\IdentidadPersonaService::class)->asignarRol([
            'nombre' => 'Carlos',
            'apellidos' => 'Silbato',
            'fecha_nacimiento' => '1988-03-12',
            'curp' => 'SILC880312HDFRRN01',
        ], 'arbitro', $this->temporada);

        $arbitro = Arbitro::create(['persona_id' => $rolArbitro->persona_id, 'estado' => 'Activo']);
        $usuarioArbitro = User::create([
            'name' => 'Carlos Silbato',
            'email' => 'arbitro_generador@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
        ]);
        $usuarioArbitro->forceFill(['persona_id' => $rolArbitro->persona_id])->save();
        $usuarioArbitro->assignRole('Árbitro en Campo');

        // Jugadores para los equipos (para permitir alineación en la cédula)
        foreach ([$e1, $e2] as $idx => $equipo) {
            for ($j = 1; $j <= 5; $j++) {
                Jugador::create([
                    'persona_id' => Persona::factory()->create(['nombre' => "Jug {$j}", 'apellidos' => "Eq {$idx}"])->id,
                    'equipo_id' => $equipo->id,
                    'dorsal' => $j,
                    'estado_elegibilidad' => 'Activo',
                ]);
            }
        }

        // 1. Generar rol y confirmar
        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-20',
            'dias_juego' => [5],
            'vueltas' => 1,
        ]);

        $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
                'sobrescribir' => '0',
            ])
            ->assertRedirect(route('grupos.jornadas.index', $this->grupo))
            ->assertSessionHas('success');

        $partido = Partido::query()->where('jornada_id', $this->grupo->jornadas()->first()->id)->firstOrFail();
        $this->assertSame(Partido::ESTADO_BORRADOR, $partido->estado);
        $this->assertNull($partido->cancha_id);
        $this->assertNull($partido->hora_partido);

        // 2. Programar el partido asignando cancha y horario SIN MOTIVO (Obligatorio 1 y 6)
        $this->actingAs($this->programador)
            ->put(route('partidos.update', $partido), [
                'equipo_local_id' => $partido->equipo_local_id,
                'equipo_visitante_id' => $partido->equipo_visitante_id,
                'cancha_id' => $cancha->id,
                'fecha_partido' => \Carbon\Carbon::parse($partido->fecha_partido)->toDateString(),
                'hora_partido' => '10:00',
                'arbitro_id' => $arbitro->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $partido->refresh();
        $this->assertSame(Partido::ESTADO_PROGRAMADO, $partido->estado);
        $this->assertEquals($cancha->id, $partido->cancha_id);
        $this->assertEquals('10:00:00', $partido->hora_partido);
        $this->assertEquals($arbitro->id, $partido->arbitro_id);
        $this->assertDatabaseMissing('reprogramaciones_partidos', ['partido_id' => $partido->id]);

        // 3. El árbitro asignado abre la cédula arbitral
        $this->actingAs($usuarioArbitro)
            ->post(route('cedulas.abrir', $partido))
            ->assertRedirect(route('cedulas.show', $partido))
            ->assertSessionHas('success');

        $cedula = CedulaArbitral::query()->where('partido_id', $partido->id)->sole();
        $this->assertSame(CedulaArbitral::ABIERTA, $cedula->estado);
    }

    /**
     * SCRUM-162: Control de sobreescritura cuando ya existen jornadas programadas.
     */
    public function test_confirmar_rechaza_si_existen_jornadas_y_no_se_autoriza_sobrescribir(): void
    {
        $this->crearEquipo('Equipo X');
        $this->crearEquipo('Equipo Y');

        Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-12',
        ]);

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo);

        $response = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
                'sobrescribir' => '0',
            ]);

        $response->assertRedirect(route('grupos.generar-rol', $this->grupo))
            ->assertSessionHas('error');

        // No debe haberse reemplazado la jornada existente
        $this->assertDatabaseCount('jornadas', 1);
    }

    /**
     * SCRUM-162: Sobreescritura limpia y segura cuando se confirma explícitamente.
     */
    public function test_confirmar_sobrescribe_limpiamente_si_se_autoriza_reemplazo(): void
    {
        $this->crearEquipo('Alfa');
        $this->crearEquipo('Beta');

        Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 99,
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-02',
        ]);

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo);

        $response = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
                'sobrescribir' => '1',
            ]);

        $response->assertRedirect(route('grupos.jornadas.index', $this->grupo))
            ->assertSessionHas('success');

        // La jornada previa 99 fue eliminada y se crearon las nuevas
        $this->assertDatabaseMissing('jornadas', ['numero_jornada' => 99]);
        $this->assertDatabaseHas('jornadas', ['numero_jornada' => 1]);
    }

    /**
     * Integridad de datos: Prohibido sobreescribir si ya hay partidos en juego o finalizados.
     */
    public function test_no_permite_sobrescribir_si_hay_partidos_finalizados_o_en_juego(): void
    {
        $e1 = $this->crearEquipo('Rojos');
        $e2 = $this->crearEquipo('Azules');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-02',
        ]);

        Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'estado' => 'finalizado',
        ]);

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo);

        $response = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
                'sobrescribir' => '1',
            ]);

        $response->assertRedirect(route('grupos.generar-rol', $this->grupo))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('partidos', ['estado' => 'finalizado']);
    }

    /**
     * Generación automática de partidos para una jornada individual existente.
     */
    public function test_generar_partidos_automaticos_para_jornada_individual(): void
    {
        $e1 = $this->crearEquipo('Equipo 1');
        $e2 = $this->crearEquipo('Equipo 2');
        $e3 = $this->crearEquipo('Equipo 3');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-25',
            'fecha_fin' => '2026-11-26',
        ]);

        $response = $this->actingAs($this->programador)
            ->post(route('jornadas.generar-partidos', $jornada));

        $response->assertRedirect(route('jornadas.show', $jornada))
            ->assertSessionHas('success');

        // La jornada debe tener 1 partido generado y descanso asignado (grupo impar)
        $jornada->refresh();
        $this->assertCount(1, $jornada->partidos);
        $this->assertNotNull($jornada->equipo_descanso_id);
    }

    /**
     * No se permite generar partidos individuales si la jornada ya cuenta con partidos.
     */
    public function test_generar_partidos_individual_falla_si_la_jornada_ya_tiene_partidos(): void
    {
        $e1 = $this->crearEquipo('Equipo A1');
        $e2 = $this->crearEquipo('Equipo B1');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-25',
            'fecha_fin' => '2026-11-26',
        ]);

        Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'estado' => 'programado',
        ]);

        $response = $this->actingAs($this->programador)
            ->post(route('jornadas.generar-partidos', $jornada));

        $response->assertRedirect(route('jornadas.show', $jornada))
            ->assertSessionHas('error');
    }

    /**
     * Regla de Negocio: Grupo debe tener al menos 2 equipos inscritos.
     */
    public function test_falla_si_grupo_tiene_menos_de_dos_equipos(): void
    {
        $this->crearEquipo('Equipo Solitario');

        $response = $this->actingAs($this->programador)
            ->get(route('grupos.generar-rol', $this->grupo));

        $response->assertRedirect(route('grupos.jornadas.index', $this->grupo))
            ->assertSessionHas('error');
    }

    /**
     * Regla de Negocio: Temporada cerrada no admite generar enfrentamientos.
     */
    public function test_falla_si_temporada_esta_cerrada(): void
    {
        $this->crearEquipo('Eq 1');
        $this->crearEquipo('Eq 2');

        $this->temporada->update(['estado' => 'cerrada']);

        $response = $this->actingAs($this->programador)
            ->get(route('grupos.generar-rol', $this->grupo));

        // JornadaPolicy deny -> 403 Forbidden
        $response->assertForbidden();
    }

    /**
     * Control de Acceso RBAC: Usuario sin permisos recibe 403 Forbidden.
     */
    public function test_usuario_sin_permiso_recibe_403_al_intentar_generar_rol(): void
    {
        $this->crearEquipo('Equipo 1');
        $this->crearEquipo('Equipo 2');

        $this->actingAs($this->usuarioSinPermiso)
            ->get(route('grupos.generar-rol', $this->grupo))
            ->assertForbidden();

        $this->actingAs($this->usuarioSinPermiso)
            ->post(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => '2026-11-20',
                'intervalo_dias' => 7,
                'dias_duracion' => 2,
                'vueltas' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->usuarioSinPermiso)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode(['jornadas' => []]),
            ])
            ->assertForbidden();
    }

    /**
     * Validaciones del formulario: Fechas e intervalos inválidos.
     */
    public function test_validacion_rechaza_intervalos_y_vueltas_invalidas(): void
    {
        $this->crearEquipo('Club A');
        $this->crearEquipo('Club B');

        $response = $this->actingAs($this->programador)
            ->post(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => 'formato-invalido',
                'intervalo_dias' => 0,
                'dias_duracion' => -1,
                'vueltas' => 5,
            ]);

        $response->assertSessionHasErrors([
            'fecha_inicio',
            'intervalo_dias',
            'dias_duracion',
            'vueltas',
        ]);
    }

    /**
     * Validación de ConfirmarRolRequest: El payload debe ser una cadena JSON válida.
     */
    public function test_confirmar_falla_si_payload_no_es_json_valido(): void
    {
        $this->crearEquipo('Club JSON 1');
        $this->crearEquipo('Club JSON 2');

        $response = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => 'invalido-no-json',
            ]);

        $response->assertSessionHasErrors(['fixture_payload']);
    }

    /**
     * Prueba de seguridad: La confirmación rechaza propuestas manipuladas en el cliente con equipos ajenos o fechas inválidas.
     */
    public function test_confirmar_rol_rechaza_propuesta_alterada_con_equipos_o_fechas_invalidas(): void
    {
        $this->crearEquipo('Equipo A');
        $this->crearEquipo('Equipo B');

        // Grupo ajeno y equipo ajeno
        $otroGrupo = Grupo::factory()->create(['temporada_id' => $this->temporada->id]);
        $clubAjeno = Club::create(['nombre' => 'Club Ajeno']);
        $equipoAjeno = Equipo::create([
            'club_id' => $clubAjeno->id,
            'grupo_id' => $otroGrupo->id,
            'nombre' => 'Equipo Forastero',
        ]);

        $propuestaBase = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-20',
        ]);

        // 1. Partido con equipo ajeno al grupo
        $propuestaAlterada1 = $propuestaBase;
        $propuestaAlterada1['jornadas'][0]['partidos'][0]['equipo_visitante_id'] = $equipoAjeno->id;

        $response1 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaAlterada1),
            ]);
        $response1->assertSessionHasErrors(['fixture_payload']);

        // 2. Mismo equipo jugando contra sí mismo (local = visitante)
        $propuestaAlterada2 = $propuestaBase;
        $localId = $propuestaAlterada2['jornadas'][0]['partidos'][0]['equipo_local_id'];
        $propuestaAlterada2['jornadas'][0]['partidos'][0]['equipo_visitante_id'] = $localId;

        $response2 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaAlterada2),
            ]);
        $response2->assertSessionHasErrors(['fixture_payload']);

        // 3. Fecha con fecha_fin anterior a fecha_inicio
        $propuestaAlterada3 = $propuestaBase;
        $propuestaAlterada3['jornadas'][0]['fecha_inicio'] = '2026-11-20';
        $propuestaAlterada3['jornadas'][0]['fecha_fin'] = '2026-11-10';

        $response3 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaAlterada3),
            ]);
        $response3->assertSessionHasErrors(['fixture_payload']);
    }

    /**
     * Prueba de serialización transaccional consecutiva (RNF-33).
     */
    public function test_generar_partidos_consecutivo_no_duplica_partidos(): void
    {
        $this->crearEquipo('Alfa');
        $this->crearEquipo('Beta');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-15',
            'fecha_fin' => '2026-11-16',
        ]);

        // Primera generación exitosa
        $this->generadorService->aplicarEnfrentamientosAJornada($jornada);
        $this->assertCount(1, $jornada->fresh()->partidos);

        // Segunda llamada inmediata (simulando condición de carrera o doble clic)
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->generadorService->aplicarEnfrentamientosAJornada($jornada);

        $this->assertCount(1, $jornada->fresh()->partidos);
    }

    /**
     * Criterio 2 (RF-06.2): Generar partidos en jornada con descanso previamente asignado respeta el descanso.
     */
    public function test_generar_partidos_en_jornada_con_descanso_existente_respeta_descanso(): void
    {
        $alfa = $this->crearEquipo('Alfa');
        $beta = $this->crearEquipo('Beta');
        $gamma = $this->crearEquipo('Gamma');

        // La jornada tiene a Gamma asignado manualmente en descanso
        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-15',
            'fecha_fin' => '2026-11-16',
            'equipo_descanso_id' => $gamma->id,
        ]);

        $this->generadorService->aplicarEnfrentamientosAJornada($jornada);

        $jornadaActualizada = $jornada->fresh(['partidos']);
        $this->assertEquals($gamma->id, $jornadaActualizada->equipo_descanso_id);
        $this->assertCount(1, $jornadaActualizada->partidos);

        $partido = $jornadaActualizada->partidos->first();
        $this->assertNotNull($partido);
        $equiposPartido = [$partido->equipo_local_id, $partido->equipo_visitante_id];

        // Gamma descansa y NO debe estar jugando en el partido
        $this->assertNotContains($gamma->id, $equiposPartido);
        $this->assertContains($alfa->id, $equiposPartido);
        $this->assertContains($beta->id, $equiposPartido);
    }

    /**
     * Auditoría de reemplazo (RNF-30): Al reemplazar un rol, los partidos previos borrados deben auditarse individualmente.
     */
    public function test_reemplazar_rol_registra_auditoria_de_partidos_eliminados(): void
    {
        $e1 = $this->crearEquipo('Equipo X');
        $e2 = $this->crearEquipo('Equipo Y');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
        ]);

        $partidoPrevio = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'fecha_partido' => '2026-11-10',
            'estado' => 'borrador',
        ]);

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-20',
        ]);

        $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
                'sobrescribir' => '1',
            ])
            ->assertRedirect(route('grupos.jornadas.index', $this->grupo));

        // Debe existir el registro de auditoría del partido eliminado
        $this->assertDatabaseHas('auditoria_logs', [
            'tabla_afectada' => 'partidos',
            'registro_id' => $partidoPrevio->id,
            'accion' => 'eliminar',
            'usuario_id' => $this->programador->id,
        ]);
    }

    /**
     * Verificación de la vista jornadas.show y partidos.show sin errores 500 con partidos generados sin cancha ni hora (Obligatorio 2).
     */
    public function test_pantalla_show_jornada_carga_correctamente_sin_error_500(): void
    {
        $this->crearEquipo('Equipo 1');
        $this->crearEquipo('Equipo 2');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
        ]);

        // 1. Abrir jornada vacía
        $responseVacia = $this->actingAs($this->programador)
            ->get(route('jornadas.show', $jornada));

        $responseVacia->assertOk()
            ->assertViewIs('jornadas.show')
            ->assertViewHas('grupo')
            ->assertViewHas('equipos')
            ->assertSee("Jornada {$jornada->numero_jornada}");

        // 2. Generar enfrentamientos automáticos (los partidos quedan sin cancha y sin hora)
        $this->generadorService->aplicarEnfrentamientosAJornada($jornada);

        // 3. Abrir jornada con partidos generados (no debe lanzar error 500 en cancha ni en hora)
        $responseConPartidos = $this->actingAs($this->programador)
            ->get(route('jornadas.show', $jornada));

        $responseConPartidos->assertOk()
            ->assertSee('Sin cancha asignada')
            ->assertSee('Sin hora');

        // 4. Abrir la vista individual del partido generado
        $partidoGenerado = $jornada->fresh()->partidos->first();
        $this->assertNotNull($partidoGenerado);

        $responsePartido = $this->actingAs($this->programador)
            ->get(route('partidos.show', $partidoGenerado));

        $responsePartido->assertOk()
            ->assertSee('Sin cancha asignada')
            ->assertSee('Sin hora');
    }

    /**
     * Balance matemático de localías (Sistema Berger): Máxima diferencia de localías de 1 partido en 1 vuelta y 0 en 2 vueltas.
     */
    public function test_algoritmo_berger_balancea_localias_equitativamente_en_grupo_par(): void
    {
        $equiposIds = range(1, 10);

        // 1 vuelta (9 jornadas)
        $rol1 = $this->generadorService->calcularRolBerger($equiposIds, 1);
        $locales = array_fill_keys($equiposIds, 0);
        $visitas = array_fill_keys($equiposIds, 0);

        foreach ($rol1 as $ronda) {
            foreach ($ronda['partidos'] as $p) {
                $locales[$p['equipo_local_id']]++;
                $visitas[$p['equipo_visitante_id']]++;
            }
        }

        foreach ($equiposIds as $id) {
            $this->assertLessThanOrEqual(1, abs($locales[$id] - $visitas[$id]), "Equipo {$id} excede balance en 1 vuelta.");
            $this->assertGreaterThan(0, $locales[$id], "Equipo {$id} no tiene partidos de local.");
            $this->assertGreaterThan(0, $visitas[$id], "Equipo {$id} no tiene partidos de visitante.");
        }

        // 2 vueltas (18 jornadas)
        $rol2 = $this->generadorService->calcularRolBerger($equiposIds, 2);
        $locales2 = array_fill_keys($equiposIds, 0);
        $visitas2 = array_fill_keys($equiposIds, 0);

        foreach ($rol2 as $ronda) {
            foreach ($ronda['partidos'] as $p) {
                $locales2[$p['equipo_local_id']]++;
                $visitas2[$p['equipo_visitante_id']]++;
            }
        }

        foreach ($equiposIds as $id) {
            $this->assertEquals($locales2[$id], $visitas2[$id], "Equipo {$id} no está 50/50 balanceado en 2 vueltas.");
            $this->assertEquals(9, $locales2[$id]);
            $this->assertEquals(9, $visitas2[$id]);
        }
    }

    /**
     * Protección de estados: Prohibido sobreescribir si hay partidos confirmados o suspendidos.
     */
    public function test_no_permite_sobrescribir_si_hay_partidos_confirmados_o_suspendidos(): void
    {
        $e1 = $this->crearEquipo('Rojos');
        $e2 = $this->crearEquipo('Azules');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-11-02',
        ]);

        $partido = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $e1->id,
            'equipo_visitante_id' => $e2->id,
            'estado' => 'confirmado',
        ]);

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
                'sobrescribir' => '1',
            ])
            ->assertRedirect(route('grupos.generar-rol', $this->grupo))
            ->assertSessionHas('error');

        // Probar también con suspendido
        $partido->update(['estado' => 'suspendido']);

        $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
                'sobrescribir' => '1',
            ])
            ->assertRedirect(route('grupos.generar-rol', $this->grupo))
            ->assertSessionHas('error');
    }

    public function test_confirmar_rol_rechaza_fechas_imposibles_sin_error_500(): void
    {
        $this->crearEquipo('Equipo A');
        $this->crearEquipo('Equipo B');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        // Fecha inexistente (rollover en Carbon si no se valida estrictamente)
        $propuesta['jornadas'][0]['fecha_inicio'] = '2026-11-31';

        $response = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuesta),
            ]);

        $response->assertSessionHasErrors(['fixture_payload']);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_confirmar_rol_rechaza_numero_jornada_invalido_o_no_correlativo(): void
    {
        $this->crearEquipo('Equipo A');
        $this->crearEquipo('Equipo B');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        // Caso 1: número fuera de rango
        $propuestaInvalida = $propuesta;
        $propuestaInvalida['jornadas'][0]['numero_jornada'] = 99999999999;

        $response1 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaInvalida),
            ]);
        $response1->assertSessionHasErrors(['fixture_payload']);

        // Caso 2: número decimal en string
        $propuestaInvalida['jornadas'][0]['numero_jornada'] = '2.7';
        $response2 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaInvalida),
            ]);
        $response2->assertSessionHasErrors(['fixture_payload']);
    }

    public function test_confirmar_rol_rechaza_propuesta_incompleta_o_con_cruces_repetidos(): void
    {
        $alfa = $this->crearEquipo('Alfa');
        $beta = $this->crearEquipo('Beta');
        $gamma = $this->crearEquipo('Gamma');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        // Caso 1: En grupo impar, las jornadas sin descanso
        $propuestaSinDescanso = $propuesta;
        foreach ($propuestaSinDescanso['jornadas'] as &$j) {
            $j['equipo_descanso_id'] = null;
        }
        unset($j);

        $response1 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaSinDescanso),
            ]);
        $response1->assertSessionHasErrors(['fixture_payload']);

        // Caso 2: El mismo cruce (Beta-Gamma) en todas las jornadas con Alfa descansando en todas
        $propuestaMismoCruce = $propuesta;
        foreach ($propuestaMismoCruce['jornadas'] as &$j) {
            $j['equipo_descanso_id'] = $alfa->id;
            $j['partidos'] = [
                ['equipo_local_id' => $beta->id, 'equipo_visitante_id' => $gamma->id],
            ];
        }
        unset($j);

        $response2 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaMismoCruce),
            ]);
        $response2->assertSessionHasErrors(['fixture_payload']);

        // Caso 3: Una sola jornada en lugar de las 3 requeridas
        $propuestaIncompleta = $propuesta;
        $propuestaIncompleta['jornadas'] = [$propuesta['jornadas'][0]];

        $response3 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaIncompleta),
            ]);
        $response3->assertSessionHasErrors(['fixture_payload']);
    }

    public function test_confirmar_rol_rechaza_fechas_cronologicamente_invalidas_o_superpuestas(): void
    {
        $this->crearEquipo('Alfa');
        $this->crearEquipo('Beta');
        $this->crearEquipo('Gamma');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        // Caso 1: Todas las jornadas con la misma fecha de inicio
        $propuestaMismaFecha = $propuesta;
        foreach ($propuestaMismaFecha['jornadas'] as &$j) {
            $j['fecha_inicio'] = '2026-11-15';
            $j['fecha_fin'] = '2026-11-16';
        }
        unset($j);

        $response1 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaMismaFecha),
            ]);
        $response1->assertSessionHasErrors(['fixture_payload']);

        // Caso 2: Jornada 1 después de la Jornada 2 (orden cronológico invertido)
        $propuestaInvertida = $propuesta;
        $propuestaInvertida['jornadas'][0]['fecha_inicio'] = '2026-11-30';
        $propuestaInvertida['jornadas'][0]['fecha_fin'] = '2026-12-01';
        $propuestaInvertida['jornadas'][1]['fecha_inicio'] = '2026-11-20';
        $propuestaInvertida['jornadas'][1]['fecha_fin'] = '2026-11-21';

        $response2 = $this->actingAs($this->programador)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode($propuestaInvertida),
            ]);
        $response2->assertSessionHasErrors(['fixture_payload']);
    }

    public function test_rutas_generar_rol_retornan_403_con_mensaje_en_espanol_de_la_politica(): void
    {
        $this->crearEquipo('Alfa');
        $this->crearEquipo('Beta');

        $delegado = $this->crearUsuario('delegado_test@ligafutbol.test', 'Delegado del Club');

        // 1. Usuario no autorizado en previsualizar-rol
        $response1 = $this->actingAs($delegado)
            ->post(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => '2026-11-15',
                'intervalo_dias' => 7,
                'dias_duracion' => 1,
                'vueltas' => 1,
            ]);

        $response1->assertStatus(403)
            ->assertSee('No cuenta con privilegios para crear jornadas.');

        // 2. Usuario no autorizado en confirmar-rol
        $response2 = $this->actingAs($delegado)
            ->post(route('grupos.confirmar-rol', $this->grupo), [
                'fixture_payload' => json_encode(['jornadas' => []]),
            ]);

        $response2->assertStatus(403)
            ->assertSee('No cuenta con privilegios para crear jornadas.');

        // 3. Temporada cerrada para usuario con rol de programador
        $this->temporada->update(['estado' => 'cerrada']);

        $response3 = $this->actingAs($this->programador)
            ->post(route('grupos.previsualizar-rol', $this->grupo), [
                'fecha_inicio' => '2026-11-15',
                'intervalo_dias' => 7,
                'dias_duracion' => 1,
                'vueltas' => 1,
            ]);

        $response3->assertStatus(403)
            ->assertSee('Una temporada cerrada no admite cambios en sus jornadas.');
    }

    public function test_formulario_generar_rol_muestra_alerta_de_error_de_sesion_y_errores_de_validacion(): void
    {
        $this->crearEquipo('Equipo A');
        $this->crearEquipo('Equipo B');

        $response = $this->actingAs($this->programador)
            ->withSession(['error' => 'Mensaje de error crítico para el usuario'])
            ->get(route('grupos.generar-rol', $this->grupo));

        $response->assertOk()
            ->assertSee('Mensaje de error crítico para el usuario');
    }

    public function test_algoritmo_berger_balancea_localias_en_grupos_impares(): void
    {
        foreach ([5, 7, 9] as $numEquipos) {
            $equiposIds = range(1, $numEquipos);

            // 1 vuelta: diferencia máxima es a lo sumo 2
            $rol1 = $this->generadorService->calcularRolBerger($equiposIds, 1);
            $locales = array_fill_keys($equiposIds, 0);
            $visitas = array_fill_keys($equiposIds, 0);

            foreach ($rol1 as $ronda) {
                foreach ($ronda['partidos'] as $p) {
                    $locales[$p['equipo_local_id']]++;
                    $visitas[$p['equipo_visitante_id']]++;
                }
            }

            foreach ($equiposIds as $id) {
                $this->assertLessThanOrEqual(2, abs($locales[$id] - $visitas[$id]), "Equipo {$id} excede balance en {$numEquipos} equipos (1 vuelta).");
                $this->assertGreaterThan(0, $locales[$id]);
                $this->assertGreaterThan(0, $visitas[$id]);
            }

            // 2 vueltas: balance exacto 50/50 (diferencia 0)
            $rol2 = $this->generadorService->calcularRolBerger($equiposIds, 2);
            $locales2 = array_fill_keys($equiposIds, 0);
            $visitas2 = array_fill_keys($equiposIds, 0);

            foreach ($rol2 as $ronda) {
                foreach ($ronda['partidos'] as $p) {
                    $locales2[$p['equipo_local_id']]++;
                    $visitas2[$p['equipo_visitante_id']]++;
                }
            }

            foreach ($equiposIds as $id) {
                $this->assertEquals($locales2[$id], $visitas2[$id], "Equipo {$id} no tiene 50/50 balance en {$numEquipos} equipos (2 vueltas).");
            }
        }
    }

    public function test_generar_partidos_individual_en_dos_jornadas_con_mismo_descanso_evita_partidos_identicos(): void
    {
        $a1 = $this->crearEquipo('E1');
        $this->crearEquipo('E2');
        $this->crearEquipo('E3');
        $this->crearEquipo('E4');
        $this->crearEquipo('E5');

        $jornada1 = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-15',
            'fecha_fin' => '2026-11-16',
            'equipo_descanso_id' => $a1->id,
        ]);

        $jornada2 = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 2,
            'fecha_inicio' => '2026-11-22',
            'fecha_fin' => '2026-11-23',
            'equipo_descanso_id' => $a1->id,
        ]);

        $this->generadorService->aplicarEnfrentamientosAJornada($jornada1);
        $this->generadorService->aplicarEnfrentamientosAJornada($jornada2);

        $partidos1 = $jornada1->fresh()->partidos->map(fn ($p) => $p->equipo_local_id.'-'.$p->equipo_visitante_id)->sort()->values()->all();
        $partidos2 = $jornada2->fresh()->partidos->map(fn ($p) => $p->equipo_local_id.'-'.$p->equipo_visitante_id)->sort()->values()->all();

        $this->assertNotEquals($partidos1, $partidos2, 'La jornada 2 no debe recibir los mismos enfrentamientos exactos de la jornada 1.');
    }

    public function test_validar_propuesta_detecta_fecha_fuera_de_temporada(): void
    {
        $this->crearEquipo('Alfa');
        $this->crearEquipo('Beta');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);
        $propuesta['jornadas'][0]['fecha_inicio'] = '2030-01-01';
        $propuesta['jornadas'][0]['fecha_fin'] = '2030-01-02';

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->generadorService->validarPropuestaRol($this->grupo, $propuesta);
    }

    public function test_validar_propuesta_detecta_equipo_descanso_jugando(): void
    {
        $alfa = $this->crearEquipo('Alfa');
        $beta = $this->crearEquipo('Beta');
        $gamma = $this->crearEquipo('Gamma');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        // Alfa descansa y a la vez juega contra Beta
        $propuesta['jornadas'][0]['equipo_descanso_id'] = $alfa->id;
        $propuesta['jornadas'][0]['partidos'] = [
            ['equipo_local_id' => $alfa->id, 'equipo_visitante_id' => $beta->id],
        ];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->generadorService->validarPropuestaRol($this->grupo, $propuesta);
    }

    public function test_validar_propuesta_detecta_equipo_duplicado_en_jornada(): void
    {
        $this->crearEquipo('Alfa');
        $beta = $this->crearEquipo('Beta');
        $gamma = $this->crearEquipo('Gamma');
        $delta = $this->crearEquipo('Delta');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        // Beta juega dos partidos en la misma jornada
        $propuesta['jornadas'][0]['partidos'] = [
            ['equipo_local_id' => $beta->id, 'equipo_visitante_id' => $gamma->id],
            ['equipo_local_id' => $beta->id, 'equipo_visitante_id' => $delta->id],
        ];

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->generadorService->validarPropuestaRol($this->grupo, $propuesta);
    }

    public function test_validar_propuesta_detecta_equipo_de_otro_grupo(): void
    {
        $this->crearEquipo('Alfa');
        $this->crearEquipo('Beta');

        $otroGrupo = Grupo::create([
            'categoria_id' => $this->grupo->categoria_id,
            'temporada_id' => $this->temporada->id,
            'nombre' => 'Grupo Ajeno',
        ]);
        $clubAjeno = Club::create(['nombre' => 'Club Ajeno']);
        $equipoAjeno = Equipo::create([
            'club_id' => $clubAjeno->id,
            'grupo_id' => $otroGrupo->id,
            'nombre' => 'Equipo Ajeno',
        ]);

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        $propuesta['jornadas'][0]['partidos'][0]['equipo_local_id'] = $equipoAjeno->id;

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->generadorService->validarPropuestaRol($this->grupo, $propuesta);
    }

    public function test_validar_propuesta_detecta_numero_jornada_repetido(): void
    {
        $this->crearEquipo('Alfa');
        $this->crearEquipo('Beta');
        $this->crearEquipo('Gamma');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        $propuesta['jornadas'][1]['numero_jornada'] = 1; // Repetido

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->generadorService->validarPropuestaRol($this->grupo, $propuesta);
    }

    public function test_validar_propuesta_detecta_equipo_contra_si_mismo(): void
    {
        $alfa = $this->crearEquipo('Alfa');
        $this->crearEquipo('Beta');

        $propuesta = $this->generadorService->generarPropuestaRol($this->grupo, [
            'fecha_inicio' => '2026-11-15',
        ]);

        $propuesta['jornadas'][0]['partidos'][0]['equipo_local_id'] = $alfa->id;
        $propuesta['jornadas'][0]['partidos'][0]['equipo_visitante_id'] = $alfa->id;

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->generadorService->validarPropuestaRol($this->grupo, $propuesta);
    }

    private function crearEquipo(string $nombre): Equipo
    {
        $this->clubCount++;
        $club = Club::create(['nombre' => 'Club '.$this->clubCount.' '.$nombre]);

        return Equipo::create([
            'club_id' => $club->id,
            'grupo_id' => $this->grupo->id,
            'nombre' => $nombre,
        ]);
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'requires_password_change' => false,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
