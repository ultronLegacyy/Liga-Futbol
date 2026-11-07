<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JugadorBajaRenovacionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_baja_de_jugador_activo_persiste_motivo_fecha_estado_y_auditoria(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado();
        $temporada = Temporada::factory()->activa()->create();
        $equipo = $this->crearEquipo($club, $temporada, 'Equipo para baja');
        $persona = $this->crearPersona();
        $jugador = $equipo->jugadores()->create(['persona_id' => $persona->id, 'estado_elegibilidad' => 'Activo']);
        $fechaBaja = now()->toDateString();

        $this->actingAs($delegado)
            ->postJson(route('jugadores.baja', $jugador), [
                'motivo' => 'Retiro solicitado por el jugador',
                'fecha_baja' => $fechaBaja,
            ])
            ->assertOk()
            ->assertJsonPath('jugador.estado', 'Baja')
            ->assertJsonPath('jugador.motivo_baja', 'Retiro solicitado por el jugador')
            ->assertJsonPath('jugador.fecha_baja', $fechaBaja);

        $this->assertDatabaseHas('jugadores_equipos', [
            'id' => $jugador->id,
            'estado_elegibilidad' => 'Baja',
            'motivo_baja' => 'Retiro solicitado por el jugador',
            'fecha_baja' => $fechaBaja,
        ]);
        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'jugador_dado_de_baja',
            'tabla_afectada' => 'jugadores_equipos',
            'registro_id' => $jugador->id,
            'motivo' => 'Baja de jugador: Retiro solicitado por el jugador',
        ]);
    }

    public function test_baja_requiere_motivo_y_fecha_y_no_modifica_al_jugador_si_faltan(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado();
        $temporada = Temporada::factory()->activa()->create();
        $equipo = $this->crearEquipo($club, $temporada, 'Equipo baja requerida');
        $jugador = $equipo->jugadores()->create(['persona_id' => $this->crearPersona()->id]);

        $this->actingAs($delegado)
            ->postJson(route('jugadores.baja', $jugador), ['motivo' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo', 'fecha_baja']);

        $this->assertDatabaseHas('jugadores_equipos', [
            'id' => $jugador->id,
            'estado_elegibilidad' => 'Activo',
            'motivo_baja' => null,
            'fecha_baja' => null,
        ]);
    }

    public function test_renovacion_crea_inscripcion_en_temporada_activa_y_conserva_la_persona(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado();
        $temporadaAnterior = Temporada::factory()->cerrada()->create();
        $temporadaActual = Temporada::factory()->activa()->create();
        $equipoAnterior = $this->crearEquipo($club, $temporadaAnterior, 'Equipo anterior');
        $equipoActual = $this->crearEquipo($club, $temporadaActual, 'Equipo actual');
        $persona = $this->crearPersona();
        $inscripcionAnterior = $equipoAnterior->jugadores()->create([
            'persona_id' => $persona->id,
            'dorsal' => 9,
            'estado_elegibilidad' => 'Activo',
        ]);

        $respuesta = $this->actingAs($delegado)
            ->postJson(route('jugadores.renovar', $inscripcionAnterior), [
                'equipo_id' => $equipoActual->id,
                'dorsal' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('inscripcion.persona_id', $persona->id)
            ->assertJsonPath('inscripcion.equipo_id', $equipoActual->id)
            ->assertJsonPath('inscripcion.temporada_id', $temporadaActual->id)
            ->assertJsonPath('inscripcion.dorsal', 10);

        $inscripcionRenovada = Jugador::query()->findOrFail($respuesta->json('inscripcion.id'));

        $this->assertNotSame($inscripcionAnterior->id, $inscripcionRenovada->id);
        $this->assertSame($persona->id, $inscripcionRenovada->persona_id);
        $this->assertSame($equipoAnterior->id, $inscripcionAnterior->fresh()->equipo_id);
        $this->assertSame(1, Persona::query()->whereKey($persona->id)->count());
        $this->assertSame(2, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'jugador_renovado',
            'tabla_afectada' => 'jugadores_equipos',
            'registro_id' => $inscripcionRenovada->id,
        ]);
    }

    /** @return array{Club, User} */
    private function crearClubYDelegado(): array
    {
        $club = Club::query()->create(['nombre' => 'Club baja-renovación '.str()->random(6)]);
        $delegado = User::query()->create([
            'name' => 'Delegado de prueba',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Directivo Club',
            'estatus' => 'activo',
            'club_id' => $club->id,
        ]);
        $rol = Role::query()->firstOrCreate(['name' => 'Delegado del Club', 'guard_name' => 'web']);
        $delegado->assignRole($rol);

        return [$club, $delegado];
    }

    private function crearEquipo(Club $club, Temporada $temporada, string $nombre): Equipo
    {
        $categoria = Categoria::factory()->create([
            'temporada_id' => $temporada->id,
            'jerarquia_salto' => 0,
        ]);
        $grupo = Grupo::factory()->create([
            'temporada_id' => $temporada->id,
            'categoria_id' => $categoria->id,
        ]);

        return $club->equipos()->create([
            'temporada_id' => $temporada->id,
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
        ]);
    }

    private function crearPersona(): Persona
    {
        return Persona::query()->create([
            'nombre' => 'Jugador de prueba',
            'apellidos' => 'Baja renovación',
            'fecha_nacimiento' => '2010-01-01',
            'curp' => str()->upper(str()->random(18)),
        ]);
    }
}
