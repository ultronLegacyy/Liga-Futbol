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

    public function test_vista_de_gestion_muestra_acciones_de_baja_renovacion_y_transferencia(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado();
        $temporadaActual = Temporada::factory()->activa()->create();
        $temporadaNueva = Temporada::factory()->activa()->create();
        $equipoOrigen = $this->crearEquipo($club, $temporadaActual, 'Equipo de origen');
        $this->crearEquipo($club, $temporadaNueva, 'Equipo para renovar');
        [$clubDestino] = $this->crearClubYDelegado('Club receptor');
        $this->crearEquipo($clubDestino, $temporadaActual, 'Equipo receptor');
        $jugador = $equipoOrigen->jugadores()->create([
            'persona_id' => $this->crearPersona()->id,
            'estado_elegibilidad' => 'Activo',
        ]);

        $this->actingAs($delegado)
            ->get(route('jugadores.gestion', $jugador))
            ->assertOk()
            ->assertSee('Dar de baja')
            ->assertSee('Renovar a otra temporada')
            ->assertSee('Transferir a otro club')
            ->assertSee('Transferencia directa')
            ->assertDontSee('Historial de estados');
    }

    public function test_vista_oculta_acciones_de_gestion_para_baja_voluntaria_y_por_transferencia(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado();
        $temporada = Temporada::factory()->activa()->create();
        $equipo = $this->crearEquipo($club, $temporada, 'Equipo de estados terminales');

        foreach (['Retiro', 'Transferencia a Club destino'] as $motivo) {
            $jugador = $equipo->jugadores()->create([
                'persona_id' => $this->crearPersona()->id,
                'estado_elegibilidad' => 'Baja',
                'motivo_baja' => $motivo,
                'fecha_baja' => now()->toDateString(),
            ]);

            $this->actingAs($delegado)
                ->get(route('jugadores.gestion', $jugador))
                ->assertOk()
                ->assertDontSee('Confirmar baja')
                ->assertDontSee('Renovar a otra temporada')
                ->assertDontSee('Transferir a otro club');
        }
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

    public function test_formulario_de_baja_redirige_a_la_ficha_con_mensaje(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado();
        $temporada = Temporada::factory()->activa()->create();
        $equipo = $this->crearEquipo($club, $temporada, 'Equipo baja formulario');
        $jugador = $equipo->jugadores()->create(['persona_id' => $this->crearPersona()->id, 'estado_elegibilidad' => 'Activo']);

        $this->actingAs($delegado)
            ->from(route('jugadores.gestion', $jugador))
            ->post(route('jugadores.baja', $jugador), [
                'motivo' => 'Cambio de residencia',
                'fecha_baja' => now()->toDateString(),
            ])
            ->assertRedirect(route('jugadores.gestion', $jugador))
            ->assertSessionHas('success', 'El jugador fue dado de baja.');

        $this->assertDatabaseHas('jugadores_equipos', [
            'id' => $jugador->id,
            'estado_elegibilidad' => 'Baja',
            'motivo_baja' => 'Cambio de residencia',
        ]);
    }

    public function test_formulario_de_baja_sin_motivo_regresa_con_errores(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado();
        $temporada = Temporada::factory()->activa()->create();
        $equipo = $this->crearEquipo($club, $temporada, 'Equipo baja formulario inválido');
        $jugador = $equipo->jugadores()->create(['persona_id' => $this->crearPersona()->id, 'estado_elegibilidad' => 'Activo']);

        $this->actingAs($delegado)
            ->from(route('jugadores.gestion', $jugador))
            ->post(route('jugadores.baja', $jugador), ['motivo' => ''])
            ->assertRedirect(route('jugadores.gestion', $jugador))
            ->assertSessionHasErrors(['motivo', 'fecha_baja']);

        $this->assertSame('Activo', $jugador->fresh()->estado_elegibilidad);
    }

    public function test_formulario_de_renovacion_redirige_a_la_ficha_de_la_nueva_inscripcion(): void
    {
        [$club, $delegado] = $this->crearClubYDelegado();
        $temporadaAnterior = Temporada::factory()->cerrada()->create();
        $temporadaActual = Temporada::factory()->activa()->create();
        $equipoAnterior = $this->crearEquipo($club, $temporadaAnterior, 'Equipo anterior formulario');
        $equipoActual = $this->crearEquipo($club, $temporadaActual, 'Equipo actual formulario');
        $persona = $this->crearPersona();
        $inscripcionAnterior = $equipoAnterior->jugadores()->create(['persona_id' => $persona->id, 'estado_elegibilidad' => 'Activo']);

        $respuesta = $this->actingAs($delegado)
            ->from(route('jugadores.gestion', $inscripcionAnterior))
            ->post(route('jugadores.renovar', $inscripcionAnterior), ['equipo_id' => $equipoActual->id]);

        $renovacion = Jugador::query()->where('persona_id', $persona->id)->where('equipo_id', $equipoActual->id)->firstOrFail();
        $respuesta->assertRedirect(route('jugadores.gestion', $renovacion))
            ->assertSessionHas('success', 'La inscripción fue renovada.');
    }

    public function test_formulario_de_transferencia_redirige_a_la_ficha_en_el_club_destino(): void
    {
        [$clubOrigen, $delegado] = $this->crearClubYDelegado();
        [$clubDestino] = $this->crearClubYDelegado();
        $temporada = Temporada::factory()->activa()->create();
        $equipoOrigen = $this->crearEquipo($clubOrigen, $temporada, 'Equipo origen formulario');
        $equipoDestino = $this->crearEquipo($clubDestino, $temporada, 'Equipo destino formulario');
        $persona = $this->crearPersona();
        $inscripcionOrigen = $equipoOrigen->jugadores()->create(['persona_id' => $persona->id, 'estado_elegibilidad' => 'Activo']);

        $respuesta = $this->actingAs($delegado)
            ->from(route('jugadores.gestion', $inscripcionOrigen))
            ->post(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id]);

        $inscripcionDestino = Jugador::query()->where('persona_id', $persona->id)->where('equipo_id', $equipoDestino->id)->firstOrFail();
        $respuesta->assertRedirect(route('jugadores.gestion', $inscripcionDestino))
            ->assertSessionHas('success');
        $this->assertSame('Baja', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame('Transferencia a '.$clubDestino->nombre, $inscripcionOrigen->fresh()->motivo_baja);
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
