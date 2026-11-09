<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
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

        $this->temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create(['categoria_id' => $categoria->id]);

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

        // C(3, 2) = 3 partidos programados
        $this->assertDatabaseCount('partidos', 3);
        $this->assertDatabaseHas('partidos', [
            'estado' => 'programado',
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
