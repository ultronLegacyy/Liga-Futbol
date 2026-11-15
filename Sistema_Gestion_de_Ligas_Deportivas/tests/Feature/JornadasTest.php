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
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * HU-027 (SCRUM-42 / SCRUM-154, SCRUM-155, SCRUM-156, SCRUM-157)
 * HU-028 (SCRUM-43 / SCRUM-158, SCRUM-159, SCRUM-160)
 *
 * Pruebas de jornadas y gestión de equipos en descanso (RF-06.1, RF-06.2, RF-05.1).
 */
class JornadasTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $programador;

    private User $tesoreroUser;

    private Temporada $temporada;

    private Grupo $grupo;

    private int $clubCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_jornadas@liga.com', 'Administrador General');
        $this->programador = $this->crearUsuario('programador_jornadas@liga.com', 'Programador de Partidos');
        $this->tesoreroUser = $this->crearUsuario('tesorero_jornadas@liga.com', 'Tesorero');

        $this->temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create(['categoria_id' => $categoria->id]);
    }

    public function test_administrador_y_programador_pueden_ver_listado_de_jornadas(): void
    {
        Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
        ]);

        $this->actingAs($this->admin)
            ->get(route('grupos.jornadas.index', $this->grupo))
            ->assertOk()
            ->assertSee('Jornada 1');

        $this->actingAs($this->programador)
            ->get(route('grupos.jornadas.index', $this->grupo))
            ->assertOk()
            ->assertSee('Jornada 1');
    }

    public function test_usuario_sin_permiso_recibe_403_al_intentar_ver_jornadas(): void
    {
        $this->actingAs($this->tesoreroUser)
            ->get(route('grupos.jornadas.index', $this->grupo))
            ->assertForbidden();
    }

    public function test_crear_jornada_con_datos_validos(): void
    {
        $payload = [
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-14',
            'fecha_fin' => '2026-11-15',
        ];

        $this->actingAs($this->programador)
            ->post(route('grupos.jornadas.store', $this->grupo), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('jornadas', [
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-14',
            'fecha_fin' => '2026-11-15',
        ]);
    }

    public function test_crear_jornada_falla_si_fecha_inicio_es_posterior_a_fecha_fin(): void
    {
        $payload = [
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-11-10',
        ];

        $this->actingAs($this->programador)
            ->post(route('grupos.jornadas.store', $this->grupo), $payload)
            ->assertSessionHasErrors('fecha_fin');

        $this->assertSame(0, $this->grupo->jornadas()->count());
    }

    public function test_crear_jornada_falla_si_numero_de_jornada_esta_duplicado_en_el_grupo(): void
    {
        Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
        ]);

        $payload = [
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-17',
            'fecha_fin' => '2026-11-18',
        ];

        $this->actingAs($this->programador)
            ->post(route('grupos.jornadas.store', $this->grupo), $payload)
            ->assertSessionHasErrors('numero_jornada');
    }

    public function test_temporada_cerrada_impide_crear_o_editar_jornadas(): void
    {
        $temporadaCerrada = Temporada::factory()->cerrada()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $temporadaCerrada->id]);
        $grupoCerrado = Grupo::factory()->create(['categoria_id' => $categoria->id]);

        $payload = [
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
        ];

        $this->actingAs($this->programador)
            ->post(route('grupos.jornadas.store', $grupoCerrado), $payload)
            ->assertForbidden();

        $this->assertSame(0, $grupoCerrado->jornadas()->count());
    }

    public function test_asignar_equipo_descanso_en_grupo_impar_exitosamente(): void
    {
        $equipo1 = $this->crearEquipo('Equipo A');
        $equipo2 = $this->crearEquipo('Equipo B');
        $equipo3 = $this->crearEquipo('Equipo C');

        $this->assertTrue($this->grupo->tieneEquiposImpares());

        $payload = [
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
            'equipo_descanso_id' => $equipo3->id,
        ];

        $this->actingAs($this->programador)
            ->post(route('grupos.jornadas.store', $this->grupo), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $jornada = $this->grupo->jornadas()->firstOrFail();
        $this->assertSame($equipo3->id, $jornada->equipo_descanso_id);
        $this->assertTrue($jornada->tieneDescanso());
        $this->assertSame('Equipo C', $jornada->equipoDescanso->nombre);
    }

    public function test_asignar_equipo_descanso_falla_si_el_equipo_no_pertenece_al_grupo(): void
    {
        $otroGrupo = Grupo::factory()->create();
        $equipoExterno = $this->crearEquipo('Equipo Externo', $otroGrupo);

        $payload = [
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
            'equipo_descanso_id' => $equipoExterno->id,
        ];

        $this->actingAs($this->programador)
            ->post(route('grupos.jornadas.store', $this->grupo), $payload)
            ->assertSessionHasErrors('equipo_descanso_id');
    }

    public function test_asignar_equipo_descanso_falla_si_el_equipo_ya_tiene_partido_programado(): void
    {
        $equipo1 = $this->crearEquipo('Tigres');
        $equipo2 = $this->crearEquipo('Leones');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
        ]);

        $cancha = \App\Models\Cancha::factory()->create();
        $arbitro = $this->crearArbitro('ARBI900101HMNRRB02');

        Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $equipo1->id,
            'equipo_visitante_id' => $equipo2->id,
            'cancha_id' => $cancha->id,
            'arbitro_id' => $arbitro->id,
            'fecha_partido' => '2026-11-10',
            'hora_partido' => '10:00:00',
            'estado' => 'programado',
        ]);

        // Intentar poner a Tigres (equipo1) como descanso debe fallar
        $this->actingAs($this->programador)
            ->patch(route('jornadas.descanso.update', $jornada), [
                'equipo_descanso_id' => $equipo1->id,
            ])
            ->assertSessionHasErrors('equipo_descanso_id');

        $this->assertNull($jornada->fresh()->equipo_descanso_id);
    }

    public function test_actualizar_descanso_via_endpoint_dedicado_y_quitar_descanso(): void
    {
        $equipo1 = $this->crearEquipo('Águilas');

        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
        ]);

        // Asignar descanso
        $this->actingAs($this->programador)
            ->patch(route('jornadas.descanso.update', $jornada), [
                'equipo_descanso_id' => $equipo1->id,
            ])
            ->assertRedirect(route('jornadas.show', $jornada))
            ->assertSessionHas('success');

        $this->assertSame($equipo1->id, $jornada->fresh()->equipo_descanso_id);

        // Quitar descanso (mandar vacío/null)
        $this->actingAs($this->programador)
            ->patch(route('jornadas.descanso.update', $jornada), [
                'equipo_descanso_id' => null,
            ])
            ->assertRedirect(route('jornadas.show', $jornada))
            ->assertSessionHas('success');

        $this->assertNull($jornada->fresh()->equipo_descanso_id);
    }

    public function test_eliminar_jornada_elimina_partidos_asociados_y_registra_auditoria(): void
    {
        $jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-11',
        ]);

        $equipo1 = $this->crearEquipo('Local');
        $equipo2 = $this->crearEquipo('Visitante');
        $cancha = \App\Models\Cancha::factory()->create();
        $arbitro = $this->crearArbitro('ARBI900101HMNRRB03');

        $partido = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $equipo1->id,
            'equipo_visitante_id' => $equipo2->id,
            'cancha_id' => $cancha->id,
            'arbitro_id' => $arbitro->id,
            'fecha_partido' => '2026-11-10',
            'hora_partido' => '10:00:00',
            'estado' => 'borrador',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('jornadas.destroy', $jornada))
            ->assertRedirect(route('grupos.jornadas.index', $this->grupo))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('jornadas', ['id' => $jornada->id]);
        $this->assertDatabaseMissing('partidos', ['id' => $partido->id]);

        $this->assertDatabaseHas('auditoria_logs', [
            'tabla_afectada' => 'jornadas',
            'accion' => 'eliminar',
            'usuario_id' => $this->admin->id,
        ]);
    }

    private function crearEquipo(string $nombre, ?Grupo $grupo = null): Equipo
    {
        $this->clubCount++;
        $club = Club::create(['nombre' => 'Club '.$this->clubCount.' '.$nombre]);

        return Equipo::create([
            'club_id' => $club->id,
            'grupo_id' => $grupo ? $grupo->id : $this->grupo->id,
            'nombre' => $nombre,
        ]);
    }

    private function crearArbitro(string $curp): \App\Models\Arbitro
    {
        $rol = app(\App\Services\IdentidadPersonaService::class)->asignarRol([
            'nombre' => 'Árbitro',
            'apellidos' => 'Prueba '.$curp,
            'fecha_nacimiento' => '1992-05-10',
            'curp' => $curp,
        ], 'arbitro', $this->temporada);

        return \App\Models\Arbitro::create(['persona_id' => $rol->persona_id, 'estado' => 'Activo']);
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
