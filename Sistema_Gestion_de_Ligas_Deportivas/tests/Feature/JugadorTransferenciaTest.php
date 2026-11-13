<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AuditService;
use App\Services\GestionJugadorService;
use App\Services\RegistroJugadorService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\DatosPrivacidadJugador;
use Tests\TestCase;

class JugadorTransferenciaTest extends TestCase
{
    use DatabaseTransactions;
    use DatosPrivacidadJugador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_transfiere_jugador_a_otro_club_en_temporada_activa_conservando_persona_y_auditando_historial(): void
    {
        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => now()->subMonths(3)->toDateString()]);
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
        $origenActualizado = $inscripcionOrigen->fresh();
        $this->assertSame('Baja', $origenActualizado->estado_elegibilidad);
        $this->assertSame('Transferencia a '.$clubDestino->nombre, $origenActualizado->motivo_baja);
        $this->assertSame(GestionJugadorService::hoy(), $origenActualizado->fecha_baja?->toDateString());
        $this->assertSame($equipoOrigen->id, $origenActualizado->equipo_id);
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

    public function test_rechaza_transferencia_si_la_categoria_destino_supera_un_nivel(): void
    {
        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => now()->subMonths(3)->toDateString()]);
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

    public function test_permite_transferencia_a_categoria_inmediatamente_inferior(): void
    {
        [$temporada, $clubOrigen, $delegado, $equipoOrigen, , , $inscripcionOrigen] = $this->prepararTransferencia('Transferencia descendente');
        [$clubDestino] = $this->crearClubYDelegado('Club destino descenso');
        $equipoDestino = $this->crearEquipo($clubDestino, $temporada, 'Destino nivel cero', 0);

        $this->actingAs($delegado)->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])->assertCreated();
        $this->assertSame('Baja', $inscripcionOrigen->fresh()->estado_elegibilidad);
    }

    public function test_permite_transferencia_a_la_misma_categoria(): void
    {
        [$temporada, , $delegado, , , , $inscripcionOrigen] = $this->prepararTransferencia('Transferencia misma categoría');
        [$clubDestino] = $this->crearClubYDelegado('Club misma categoría');
        $equipoDestino = $this->crearEquipo($clubDestino, $temporada, 'Destino nivel uno', 1);

        $this->actingAs($delegado)->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])->assertCreated();
    }

    public function test_permite_bajar_mas_de_un_nivel_como_en_hu_012(): void
    {
        // HU-012 criterio 2 solo bloquea subir más de 1 nivel; la dirección está pendiente del PO.
        [$temporada, , $delegado, , , $persona, $inscripcionOrigen] = $this->prepararTransferencia('Transferencia salto descendente');
        [$clubDestino] = $this->crearClubYDelegado('Club salto descendente');
        $equipoDestino = $this->crearEquipo($clubDestino, $temporada, 'Destino nivel menos uno', -1);

        $this->actingAs($delegado)->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])->assertCreated();
        $this->assertSame(2, Jugador::query()->where('persona_id', $persona->id)->count());
    }

    public function test_baja_por_transferencia_no_bloquea_una_segunda_transferencia_en_la_misma_temporada(): void
    {
        [$temporada, , $delegado, , $equipoDestino, $persona, $inscripcionOrigen] = $this->prepararTransferencia('Doble transferencia');
        $respuesta = $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertCreated();
        $inscripcionIntermedia = Jugador::query()->findOrFail($respuesta->json('transferencia.jugador_destino_id'));
        [$clubTercero] = $this->crearClubYDelegado('Club tercero');
        $equipoTercero = $this->crearEquipo($clubTercero, $temporada, 'Equipo tercero', 2);
        $administrador = User::query()->create([
            'name' => 'Administrador transferencias',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Administrador General',
            'estatus' => 'activo',
        ]);
        $administrador->assignRole(Role::query()->firstOrCreate(['name' => 'Administrador General', 'guard_name' => 'web']));

        $this->actingAs($administrador)
            ->postJson(route('jugadores.transferir', $inscripcionIntermedia), ['equipo_id' => $equipoTercero->id])
            ->assertCreated();

        $this->assertSame('Baja', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame('Baja', $inscripcionIntermedia->fresh()->estado_elegibilidad);
        $this->assertSame(1, Jugador::query()->where('persona_id', $persona->id)->where('estado_elegibilidad', 'Activo')->count());
        $this->assertDatabaseCount('historial_transferencias_jugador', 2);
    }

    public function test_rechaza_transferencia_si_el_jugador_no_cumple_edad_de_destino(): void
    {
        [$temporada, , $delegado, , , $persona, $inscripcionOrigen] = $this->prepararTransferencia('Transferencia por edad');
        [$clubDestino] = $this->crearClubYDelegado('Club edad destino');
        $equipoDestino = $this->crearEquipo($clubDestino, $temporada, 'Destino edad mínima', 2);
        $equipoDestino->grupo->categoria->update(['edad_min' => 21]);

        $this->actingAs($delegado)->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])->assertUnprocessable()->assertJsonValidationErrors('equipo_id');
        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame(1, Jugador::query()->where('persona_id', $persona->id)->count());
    }

    public function test_baja_bloquea_una_inscripcion_en_otro_equipo_de_la_misma_temporada(): void
    {
        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => now()->subMonths(3)->toDateString()]);
        [$club, $delegado] = $this->crearClubYDelegado('Club baja registro mismo ciclo');
        $equipoOrigen = $this->crearEquipo($club, $temporada, 'Equipo con baja', 1);
        $equipoDestino = $this->crearEquipo($club, $temporada, 'Equipo alterno baja', 1);
        $persona = $this->crearPersona();
        $equipoOrigen->jugadores()->create(['persona_id' => $persona->id, 'estado_elegibilidad' => 'Baja', 'motivo_baja' => 'Retiro', 'fecha_baja' => GestionJugadorService::hoy()]);

        try {
            app(RegistroJugadorService::class)->registrar($equipoDestino, ['persona_id' => $persona->id] + $this->privacidad(), $delegado);
            $this->fail('Una inscripción Baja debe bloquear otra inscripción en la misma temporada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('persona_id', $exception->errors());
        }

        $this->assertSame(1, Jugador::query()->where('persona_id', $persona->id)->count());
    }

    public function test_transferido_no_bloquea_una_nueva_inscripcion_en_una_temporada_posterior(): void
    {
        [$temporada, $clubOrigen, $delegado, , $equipoDestino, $persona, $inscripcionOrigen] = $this->prepararTransferencia('Nueva temporada tras transferencia');
        $this->actingAs($delegado)->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])->assertCreated();
        $temporadaSiguiente = Temporada::factory()->activa()->create(['fecha_inicio' => now()->subMonths(3)->toDateString()]);
        $equipoSiguiente = $this->crearEquipo($clubOrigen, $temporadaSiguiente, 'Nuevo equipo después de transferencia', 2);

        $nuevaInscripcion = app(RegistroJugadorService::class)->registrar($equipoSiguiente, ['persona_id' => $persona->id] + $this->privacidad(), $delegado);

        $this->assertSame($persona->id, $nuevaInscripcion->persona_id);
        $this->assertSame($equipoSiguiente->id, $nuevaInscripcion->equipo_id);
        $this->assertSame('Activo', $nuevaInscripcion->fresh()->estado_elegibilidad);
        $this->assertSame(3, Jugador::query()->where('persona_id', $persona->id)->count());
    }

    public function test_rechaza_con_422_a_un_jugador_que_no_esta_activo(): void
    {
        [$temporada, $clubOrigen, $delegado, $equipoOrigen, $equipoDestino, $persona, $inscripcionOrigen] = $this->prepararTransferencia('Jugador suspendido');
        $inscripcionOrigen->update(['estado_elegibilidad' => 'Suspendido']);

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jugador');

        $this->assertSame('Suspendido', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame(1, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    public function test_responde_404_cuando_el_jugador_no_existe(): void
    {
        [, , $delegado, , $equipoDestino] = $this->prepararTransferencia('Jugador inexistente');

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', 999999999), ['equipo_id' => $equipoDestino->id])
            ->assertNotFound();

        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    public function test_responde_403_cuando_el_delegado_no_pertenece_al_club_de_origen(): void
    {
        [, , , $equipoOrigen, $equipoDestino, , $inscripcionOrigen] = $this->prepararTransferencia('Jugador de otro club');
        [, $delegadoAjeno] = $this->crearClubYDelegado('Delegado ajeno a transferencia');

        $this->actingAs($delegadoAjeno)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertForbidden();

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame($equipoOrigen->id, $inscripcionOrigen->fresh()->equipo_id);
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    public function test_responde_422_cuando_el_equipo_destino_no_existe(): void
    {
        [, , $delegado, , , , $inscripcionOrigen] = $this->prepararTransferencia('Destino inexistente');

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => 999999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipo_id');

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    public function test_rechaza_equipo_destino_del_mismo_club(): void
    {
        [$temporada, $clubOrigen, $delegado, , , $persona, $inscripcionOrigen] = $this->prepararTransferencia('Destino mismo club');
        $equipoMismoClub = $this->crearEquipo($clubOrigen, $temporada, 'Segundo equipo del mismo club', 2);

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoMismoClub->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipo_id');

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame(1, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    public function test_rechaza_destino_cuyo_equipo_grupo_categoria_y_temporada_no_coinciden(): void
    {
        [, , $delegado, , , $persona, $inscripcionOrigen] = $this->prepararTransferencia('Destino inconsistente');
        [$clubDestino] = $this->crearClubYDelegado('Club con temporada destino inconsistente');
        $temporadaEquipo = Temporada::factory()->activa()->create(['fecha_inicio' => now()->subMonths(3)->toDateString()]);
        $temporadaGrupo = Temporada::factory()->activa()->create(['fecha_inicio' => now()->subMonths(3)->toDateString()]);
        $categoria = Categoria::factory()->create(['temporada_id' => $temporadaGrupo->id, 'jerarquia_salto' => 2]);
        $grupo = Grupo::factory()->create([
            'categoria_id' => $categoria->id,
            'temporada_id' => $temporadaGrupo->id,
        ]);
        $equipoDestino = $clubDestino->equipos()->create([
            'temporada_id' => $temporadaEquipo->id,
            'grupo_id' => $grupo->id,
            'nombre' => 'Equipo destino inconsistente',
        ]);

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipo_id');

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame(1, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    public function test_rechaza_transferencia_si_la_persona_ya_tiene_otra_inscripcion_activa_en_la_temporada_destino(): void
    {
        [$temporada, , $delegado, , $equipoDestino, $persona, $inscripcionOrigen] = $this->prepararTransferencia('Inscripcion destino duplicada');
        $equipoDestino->jugadores()->create([
            'persona_id' => $persona->id,
            'dorsal' => 9,
            'estado_elegibilidad' => 'Activo',
        ]);

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jugador');

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame(2, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertSame(2, Jugador::query()
            ->where('persona_id', $persona->id)
            ->where('estado_elegibilidad', 'Activo')
            ->whereHas('equipo', fn ($query) => $query->where('temporada_id', $temporada->id))
            ->count());
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    #[DataProvider('estadosQueBloqueanTransferencia')]
    public function test_rechaza_transferencia_si_hay_estado_bloqueante_en_otro_equipo_de_la_temporada(string $estado): void
    {
        [$temporada, $clubOrigen, $delegado, , $equipoDestino, $persona, $inscripcionOrigen] = $this->prepararTransferencia('Estado bloqueante '.$estado);
        $equipoConEstadoBloqueante = $this->crearEquipo($clubOrigen, $temporada, 'Equipo '.$estado, 1);
        $equipoConEstadoBloqueante->jugadores()->create([
            'persona_id' => $persona->id,
            'estado_elegibilidad' => $estado,
        ]);

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jugador');

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame(2, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    public static function estadosQueBloqueanTransferencia(): array
    {
        return [
            'Suspendido' => ['Suspendido'],
            'Inelegible' => ['Inelegible'],
            'Baja' => ['Baja'],
        ];
    }

    public function test_no_crea_una_segunda_transferencia_para_la_misma_inscripcion(): void
    {
        [, , $delegado, , $equipoDestino, $persona, $inscripcionOrigen] = $this->prepararTransferencia('Transferencia repetida');

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertCreated();

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jugador');

        $this->assertSame('Baja', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame(2, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertDatabaseCount('historial_transferencias_jugador', 1);
        $this->assertSame(1, $this->auditoriasDeTransferencia()->count());
    }

    public function test_administrador_general_puede_realizar_una_transferencia_directa(): void
    {
        [, , , , $equipoDestino, $persona, $inscripcionOrigen] = $this->prepararTransferencia('Transferencia administrativa');
        $administrador = User::query()->create([
            'name' => 'Administrador de transferencias',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'rol' => 'Administrador General',
            'estatus' => 'activo',
        ]);
        $administrador->assignRole(Role::query()->firstOrCreate([
            'name' => 'Administrador General',
            'guard_name' => 'web',
        ]));

        $respuesta = $this->actingAs($administrador)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertCreated();

        $this->assertDatabaseHas('historial_transferencias_jugador', [
            'id' => $respuesta->json('transferencia.id'),
            'persona_id' => $persona->id,
            'transferido_por_usuario_id' => $administrador->id,
        ]);
        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $administrador->id,
            'accion' => 'jugador_transferido',
            'tabla_afectada' => 'historial_transferencias_jugador',
            'registro_id' => $respuesta->json('transferencia.id'),
        ]);
    }

    public function test_rechaza_campos_de_origen_ejecutor_estado_e_historial_en_el_payload(): void
    {
        [, , $delegado, , $equipoDestino, , $inscripcionOrigen] = $this->prepararTransferencia('Payload manipulado');

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), [
                'equipo_id' => $equipoDestino->id,
                'club_origen_id' => 999,
                'transferido_por_usuario_id' => 999,
                'estado_elegibilidad' => 'Baja',
                'historial_transferencia_id' => 999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'club_origen_id',
                'transferido_por_usuario_id',
                'estado_elegibilidad',
                'historial_transferencia_id',
            ]);

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
    }

    public function test_revierte_inscripcion_destino_historial_y_auditoria_si_falla_la_auditoria_dentro_de_la_transaccion(): void
    {
        [, , $delegado, , $equipoDestino, $persona, $inscripcionOrigen] = $this->prepararTransferencia('Rollback transferencia');
        $auditoria = \Mockery::mock(AuditService::class)->makePartial();
        $auditoria->shouldReceive('registrar')
            ->once()
            ->andThrow(new RuntimeException('Fallo inducido al guardar la auditoría.'));
        $this->app->instance(AuditService::class, $auditoria);

        $this->actingAs($delegado)
            ->postJson(route('jugadores.transferir', $inscripcionOrigen), ['equipo_id' => $equipoDestino->id])
            ->assertServerError();

        $this->assertSame('Activo', $inscripcionOrigen->fresh()->estado_elegibilidad);
        $this->assertSame(1, Jugador::query()->where('persona_id', $persona->id)->count());
        $this->assertDatabaseCount('historial_transferencias_jugador', 0);
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
            'fecha_nacimiento' => '2006-01-01',
            'curp' => str()->upper(str()->random(18)),
        ]);
    }

    /** @return array{Temporada, Club, User, Equipo, Equipo, Persona, Jugador} */
    private function prepararTransferencia(string $nombre): array
    {
        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => now()->subMonths(3)->toDateString()]);
        [$clubOrigen, $delegado] = $this->crearClubYDelegado($nombre.' origen');
        [$clubDestino] = $this->crearClubYDelegado($nombre.' destino');
        $equipoOrigen = $this->crearEquipo($clubOrigen, $temporada, $nombre.' equipo origen', 1);
        $equipoDestino = $this->crearEquipo($clubDestino, $temporada, $nombre.' equipo destino', 2);
        $persona = $this->crearPersona();
        $inscripcionOrigen = $equipoOrigen->jugadores()->create([
            'persona_id' => $persona->id,
            'dorsal' => 8,
            'estado_elegibilidad' => 'Activo',
        ]);

        return [$temporada, $clubOrigen, $delegado, $equipoOrigen, $equipoDestino, $persona, $inscripcionOrigen];
    }

    private function auditoriasDeTransferencia(): Builder
    {
        return AuditoriaLog::query()
            ->where('accion', 'jugador_transferido')
            ->where('tabla_afectada', 'historial_transferencias_jugador');
    }
}
