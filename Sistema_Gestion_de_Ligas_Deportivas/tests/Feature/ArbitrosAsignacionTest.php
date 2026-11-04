<?php

namespace Tests\Feature;

use App\Models\Arbitro;
use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use App\Models\User;
use App\Services\IdentidadPersonaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// HU-026: padrón de árbitros colegiados y su asignación a partidos programados (RF-03.2, RF-06.1)
class ArbitrosAsignacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private Temporada $temporada;

    private Jornada $jornada;

    private Cancha $cancha;

    private Arbitro $arbitro;

    private int $equipos = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_arbitros@liga.com', 'Administrador General');
        $this->temporada = Temporada::factory()->activa()->create();

        $categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id]);
        $this->jornada = Jornada::create([
            'grupo_id' => $grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-07',
            'fecha_fin' => '2026-11-08',
        ]);
        $this->cancha = Cancha::factory()->create(['nombre' => 'Cancha de prueba 1']);

        $this->arbitro = $this->arbitroConRol('ARBI900101HMNRRB01');
    }

    // CA1: una persona con rol de árbitro se registra en el padrón y queda disponible para asignarse
    public function test_una_persona_con_rol_arbitro_se_registra_y_queda_disponible(): void
    {
        $rol = app(IdentidadPersonaService::class)->asignarRol([
            'nombre' => 'Nora',
            'apellidos' => 'Silbato Ficticia',
            'fecha_nacimiento' => '1988-03-03',
            'curp' => 'NOSI880303MMNLLR02',
        ], 'arbitro', $this->temporada);

        $this->actingAs($this->admin)
            ->post(route('arbitros.store'), [
                'persona_id' => $rol->persona_id,
                'colegio_pertenencia' => 'Colegio de prueba',
                'tarifa_por_partido' => '125.00',
                'estado' => 'Activo',
            ])
            ->assertRedirect(route('arbitros.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('arbitros', ['persona_id' => $rol->persona_id, 'estado' => 'Activo']);

        $partido = $this->partido('2026-11-07', '10:00:00');
        $this->actingAs($this->admin)
            ->get(route('partidos.arbitro.edit', $partido))
            ->assertOk()
            ->assertSee('Silbato Ficticia, Nora');
    }

    // CA1: sin rol de árbitro la persona no entra al padrón
    public function test_una_persona_sin_rol_arbitro_no_entra_al_padron(): void
    {
        $persona = Persona::factory()->create();
        PersonaRol::factory()->create(['persona_id' => $persona->id, 'rol' => 'jugador', 'temporada_id' => $this->temporada->id]);

        $this->actingAs($this->admin)
            ->post(route('arbitros.store'), ['persona_id' => $persona->id, 'estado' => 'Activo'])
            ->assertSessionHasErrors('persona_id');

        $this->assertDatabaseMissing('arbitros', ['persona_id' => $persona->id]);
    }

    // CA2: la asignación queda registrada con local, visitante, cancha, fecha y hora
    public function test_la_asignacion_conserva_los_datos_del_partido(): void
    {
        $partido = $this->partido('2026-11-07', '10:00:00');
        $antes = $partido->only(['equipo_local_id', 'equipo_visitante_id', 'cancha_id']);

        $this->actingAs($this->admin)
            ->put(route('partidos.arbitro.update', $partido), ['arbitro_id' => $this->arbitro->id])
            ->assertRedirect(route('arbitros.asignaciones'))
            ->assertSessionHasNoErrors();

        $partido->refresh();
        $this->assertSame($this->arbitro->id, $partido->arbitro_id);
        $this->assertSame($antes, $partido->only(['equipo_local_id', 'equipo_visitante_id', 'cancha_id']));
        $this->assertSame('2026-11-07', $partido->fecha_partido->format('Y-m-d'));
        $this->assertSame('10:00:00', $partido->hora_partido);

        $this->actingAs($this->admin)
            ->get(route('arbitros.asignaciones'))
            ->assertOk()
            ->assertSeeInOrder(['07/11/2026 · 10:00', $partido->local->nombre, $partido->visitante->nombre, 'Cancha de prueba 1', 'Ficticio']);
    }

    // CA3: el mismo árbitro no se asigna a otro partido a la misma fecha y hora
    public function test_se_rechaza_un_segundo_partido_en_el_mismo_horario(): void
    {
        $primero = $this->partido('2026-11-07', '10:00:00');
        $segundo = $this->partido('2026-11-07', '10:00:00');

        $this->actingAs($this->admin)->put(route('partidos.arbitro.update', $primero), ['arbitro_id' => $this->arbitro->id]);

        $this->actingAs($this->admin)
            ->from(route('partidos.arbitro.edit', $segundo))
            ->put(route('partidos.arbitro.update', $segundo), ['arbitro_id' => $this->arbitro->id])
            ->assertRedirect(route('partidos.arbitro.edit', $segundo))
            ->assertSessionHasErrors(['arbitro_id' => 'El árbitro ya tiene asignado otro partido a la misma fecha y hora.']);

        $this->assertNull($segundo->fresh()->arbitro_id);
        $this->assertSame($this->arbitro->id, $primero->fresh()->arbitro_id);
    }

    // Otro horario del mismo día sí se permite: no se inventa una duración de partido
    public function test_otro_horario_del_mismo_dia_si_se_permite(): void
    {
        $primero = $this->partido('2026-11-07', '10:00:00');
        $segundo = $this->partido('2026-11-07', '12:00:00');

        $this->actingAs($this->admin)->put(route('partidos.arbitro.update', $primero), ['arbitro_id' => $this->arbitro->id]);
        $this->actingAs($this->admin)
            ->put(route('partidos.arbitro.update', $segundo), ['arbitro_id' => $this->arbitro->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->arbitro->id, $segundo->fresh()->arbitro_id);
    }

    // CA3 ante solicitudes simultáneas: el índice único de la base rechaza el segundo registro
    public function test_la_base_impide_el_choque_aunque_se_salte_la_aplicacion(): void
    {
        $this->partido('2026-11-07', '10:00:00')->update(['arbitro_id' => $this->arbitro->id]);
        $segundo = $this->partido('2026-11-07', '10:00:00');

        $this->expectException(QueryException::class);

        $segundo->update(['arbitro_id' => $this->arbitro->id]);
    }

    public function test_un_arbitro_inactivo_o_sin_rol_en_la_temporada_no_se_asigna(): void
    {
        $partido = $this->partido('2026-11-07', '10:00:00');

        $this->arbitro->update(['estado' => 'Inactivo']);
        $this->actingAs($this->admin)
            ->put(route('partidos.arbitro.update', $partido), ['arbitro_id' => $this->arbitro->id])
            ->assertSessionHasErrors('arbitro_id');

        // Árbitro activo pero con rol solo en otra temporada
        $otro = $this->arbitroConRol('OTRA900101HMNRRB02', Temporada::factory()->activa()->create());
        $this->actingAs($this->admin)
            ->put(route('partidos.arbitro.update', $partido), ['arbitro_id' => $otro->id])
            ->assertSessionHasErrors('arbitro_id');

        $this->assertNull($partido->fresh()->arbitro_id);
    }

    public function test_no_se_asigna_a_partidos_en_borrador_ni_sin_horario(): void
    {
        $borrador = $this->partido('2026-11-07', '10:00:00', 'borrador');
        $sinHora = $this->partido('2026-11-07', null);

        foreach ([$borrador, $sinHora] as $partido) {
            $this->actingAs($this->admin)
                ->put(route('partidos.arbitro.update', $partido), ['arbitro_id' => $this->arbitro->id])
                ->assertSessionHasErrors('arbitro_id');

            $this->assertNull($partido->fresh()->arbitro_id);
        }
    }

    // RF-01.4: los partidos de una temporada cerrada solo se consultan
    public function test_no_se_asigna_en_una_temporada_cerrada(): void
    {
        $partido = $this->partido('2026-11-07', '10:00:00');
        $this->temporada->update(['estado' => 'cerrada']);

        $this->actingAs($this->admin)
            ->put(route('partidos.arbitro.update', $partido), ['arbitro_id' => $this->arbitro->id])
            ->assertSessionHasErrors('arbitro_id');

        $this->assertNull($partido->fresh()->arbitro_id);
    }

    public function test_sin_permisos_de_arbitraje_se_responde_403(): void
    {
        $presidente = $this->crearUsuario('presidente_arbitros@liga.com', 'Presidente');
        $partido = $this->partido('2026-11-07', '10:00:00');

        $this->actingAs($presidente)->get(route('arbitros.index'))->assertForbidden();
        $this->actingAs($presidente)
            ->put(route('partidos.arbitro.update', $partido), ['arbitro_id' => $this->arbitro->id])
            ->assertForbidden();

        $this->assertNull($partido->fresh()->arbitro_id);
    }

    public function test_se_actualiza_la_disponibilidad_del_arbitro(): void
    {
        $this->actingAs($this->admin)
            ->put(route('arbitros.update', $this->arbitro), ['colegio_pertenencia' => 'Colegio B', 'tarifa_por_partido' => '', 'estado' => 'Inactivo'])
            ->assertRedirect(route('arbitros.index'))
            ->assertSessionHasNoErrors();

        $this->arbitro->refresh();
        $this->assertSame('Inactivo', $this->arbitro->estado);
        $this->assertSame('0.00', $this->arbitro->tarifa_por_partido);
    }

    private function arbitroConRol(string $curp, ?Temporada $temporada = null): Arbitro
    {
        $rol = app(IdentidadPersonaService::class)->asignarRol([
            'nombre' => 'Árbitro',
            'apellidos' => 'Ficticio '.$curp,
            'fecha_nacimiento' => '1990-01-01',
            'curp' => $curp,
        ], 'arbitro', $temporada ?? $this->temporada);

        return Arbitro::create(['persona_id' => $rol->persona_id, 'estado' => 'Activo']);
    }

    private function partido(string $fecha, ?string $hora, string $estado = 'programado'): Partido
    {
        return Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->equipo()->id,
            'equipo_visitante_id' => $this->equipo()->id,
            'cancha_id' => $this->cancha->id,
            'fecha_partido' => $fecha,
            'hora_partido' => $hora,
            'estado' => $estado,
        ]);
    }

    private function equipo(): Equipo
    {
        $this->equipos++;
        $club = Club::create(['nombre' => 'Club de prueba '.$this->equipos]);

        return Equipo::create([
            'club_id' => $club->id,
            'grupo_id' => $this->jornada->grupo_id,
            'nombre' => 'Equipo de prueba '.$this->equipos,
        ]);
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
