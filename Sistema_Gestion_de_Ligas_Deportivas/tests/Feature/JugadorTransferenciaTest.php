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

class JugadorTransferenciaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_transfiere_jugador_a_otro_club_en_temporada_activa_conservando_persona_y_auditando_historial(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        [$clubOrigen, $delegado] = $this->crearClubYDelegado('Club origen transferencia');
        [$clubDestino] = $this->crearClubYDelegado('Club destino transferencia');
        $equipoOrigen = $this->crearEquipo($clubOrigen, $temporada, 'Equipo origen', 1);
        $equipoDestino = $this->crearEquipo($clubDestino, $temporada, 'Equipo destino', 2);
        $persona = $this->crearPersona();
        $inscripcionOrigen = $equipoOrigen->jugadores()->create([
            'persona_id' => $persona->id,
            'dorsal' => 8,
            'estado_elegibilidad' => 'Activo',
        ]);

        $respuesta = $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), [
                'equipo_id' => $equipoDestino->id,
                'dorsal' => 11,
            ])
            ->assertCreated()
            ->assertJsonPath('transferencia.persona_id', $persona->id)
            ->assertJsonPath('transferencia.club_origen_id', $clubOrigen->id)
            ->assertJsonPath('transferencia.club_destino_id', $clubDestino->id)
            ->assertJsonPath('transferencia.equipo_origen_id', $equipoOrigen->id)
            ->assertJsonPath('transferencia.equipo_destino_id', $equipoDestino->id)
            ->assertJsonPath('transferencia.temporada_id', $temporada->id);

        $inscripcionDestino = Jugador::query()->findOrFail($respuesta->json('transferencia.jugador_destino_id'));
        $this->assertSame($persona->id, $inscripcionDestino->persona_id);
        $this->assertSame($equipoDestino->id, $inscripcionDestino->equipo_id);
        $this->assertSame('Activo', $inscripcionDestino->estado_elegibilidad);
        $this->assertSame('Transferido', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame($equipoOrigen->id, $inscripcionOrigen->fresh()->equipo_id);
        $this->assertSame(1, Persona::query()->whereKey($persona->id)->count());

        $this->assertDatabaseHas('historial_transferencias_jugador', [
            'id' => $respuesta->json('transferencia.id'),
            'persona_id' => $persona->id,
            'jugador_origen_id' => $inscripcionOrigen->id,
            'jugador_destino_id' => $inscripcionDestino->id,
            'transferido_por_usuario_id' => $delegado->id,
        ]);
        $this->assertDatabaseHas('auditoria_logs', [
            'accion' => 'jugador_transferido',
            'tabla_afectada' => 'historial_transferencias_jugador',
            'registro_id' => $respuesta->json('transferencia.id'),
        ]);
    }

    public function test_rechaza_transferencia_si_la_categoria_destino_supera_dos_niveles(): void
    {
        $temporada = Temporada::factory()->activa()->create();
        [$clubOrigen, $delegado] = $this->crearClubYDelegado('Club origen salto');
        [$clubDestino] = $this->crearClubYDelegado('Club destino salto');
        $equipoOrigen = $this->crearEquipo($clubOrigen, $temporada, 'Equipo nivel uno', 1);
        $equipoDestino = $this->crearEquipo($clubDestino, $temporada, 'Equipo nivel tres', 3);
        $persona = $this->crearPersona();
        $inscripcionOrigen = $equipoOrigen->jugadores()->create([
            'persona_id' => $persona->id,
            'estado_elegibilidad' => 'Activo',
        ]);

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categoria_id');

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
        $this->assertSame(1, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertDatabaseMissing('auditoria_logs', [
            'accion' => 'jugador_transferido',
            'tabla_afectada' => 'historial_transferencias_jugador',
        ]);
    }

    /** @return array{Club, User} */
    private function crearClubYDelegado(string $nombre): array
    {
        $club = Club::query()->create(['nombre' => $nombre.' '.str()->random(5)]);
        $delegado = User::query()->create([
            'name' => 'Delegado de '.$nombre,
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

    private function crearEquipo(Club $club, Temporada $temporada, string $nombre, int $jerarquia): Equipo
    {
        $categoria = Categoria::factory()->create([
            'temporada_id' => $temporada->id,
            'jerarquia_salto' => $jerarquia,
        ]);
        $grupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $temporada->id,
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
            'nombre' => 'Jugador Transferencia',
            'apellidos' => 'Prueba HU-017',
            'fecha_nacimiento' => '2010-01-01',
            'curp' => str()->upper(str()->random(18)),
        ]);
    }
}
