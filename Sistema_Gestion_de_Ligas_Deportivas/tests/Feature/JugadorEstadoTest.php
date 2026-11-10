<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\HistorialEstadoJugador;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EstadoJugadorService;
use App\Services\GestionJugadorService;
use App\Services\VentaClubService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** HU-018 (SCRUM-33): los 4 estados del jugador y el registro de quién y por qué de cada cambio. */
class JugadorEstadoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    // --- Criterio 1: la consulta muestra uno de los 4 estados ---

    public function test_al_consultar_un_jugador_se_muestra_uno_de_los_4_estados(): void
    {
        [$jugador, $delegado] = $this->crearJugadorYDelegado('Suspendido');

        $this->actingAs($delegado)
            ->getJson(route('jugadores.show', $jugador))
            ->assertOk()
            ->assertJsonPath('jugador.estado', 'Suspendido');
        $this->assertSame(['Activo', 'Suspendido', 'Baja', 'Inelegible'], Jugador::ESTADOS_ELEGIBILIDAD);
    }

    public function test_el_secretario_llega_a_la_ficha_desde_el_menu_y_el_listado(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo');
        $jugador->persona->update(['nombre' => 'Visible', 'apellidos' => 'Para Secretario']);

        $this->actingAs($secretario)->get(route('jugadores.index'))
            ->assertOk()
            ->assertSee(route('jugadores.index'))
            ->assertSee('Visible Para Secretario')
            ->assertSee(route('jugadores.gestion', $jugador));
        $this->get(route('jugadores.gestion', $jugador))->assertOk()->assertSee('Estado actual');
    }

    // --- Criterio 2: quién y por qué de cada cambio ---

    public function test_secretario_cambia_estado_y_guarda_motivo_actor_fecha_y_una_sola_auditoria(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo');

        $this->actingAs($secretario)
            ->putJson(route('jugadores.estado.update', $jugador), [
                'estado_elegibilidad' => 'Suspendido',
                'motivo' => 'Sanción disciplinaria registrada',
            ])
            ->assertOk()
            ->assertJsonPath('jugador.estado_elegibilidad', 'Suspendido');

        $this->assertDatabaseHas('historial_estados_jugador', [
            'jugador_id' => $jugador->id,
            'persona_id' => $jugador->persona_id,
            'estado_anterior' => 'Activo',
            'estado_nuevo' => 'Suspendido',
            'motivo' => 'Sanción disciplinaria registrada',
            'usuario_id' => $secretario->id,
        ]);
        $this->assertNotNull($jugador->historialEstados()->first()?->created_at);
        // Una sola entrada de auditoría por el cambio (la del trait Auditable de Jugador).
        $auditorias = AuditoriaLog::query()->where('tabla_afectada', 'jugadores_equipos')->where('registro_id', $jugador->id)->where('accion', '!=', 'crear')->get();
        $this->assertCount(1, $auditorias);
        $this->assertSame('actualizar', $auditorias->first()->accion);
        $this->assertSame('Cambio de estado del jugador: Sanción disciplinaria registrada', $auditorias->first()->motivo);
        $this->assertSame($secretario->id, (int) $auditorias->first()->usuario_id);
    }

    public function test_cambios_consecutivos_conservan_todas_las_entradas_del_historial(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo');
        $this->actingAs($secretario);

        foreach ([['Suspendido', 'Sanción temporal'], ['Inelegible', 'Documentación pendiente'], ['Activo', 'Documentación completa']] as [$estado, $motivo]) {
            $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => $estado, 'motivo' => $motivo])->assertOk();
        }

        $this->assertSame(
            [['Activo', 'Suspendido'], ['Suspendido', 'Inelegible'], ['Inelegible', 'Activo']],
            $jugador->historialEstados()->reorder('id')->get()->map(fn (HistorialEstadoJugador $cambio): array => [$cambio->estado_anterior, $cambio->estado_nuevo])->all(),
        );
    }

    public function test_la_ficha_muestra_el_historial_con_la_hora_de_mexico(): void
    {
        // 01:29 del 10/11 en UTC son las 19:29 del 09/11 en Ciudad de México.
        $this->travelTo(Carbon::parse('2026-11-10 01:29:00', 'UTC'));
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo');

        $this->actingAs($secretario)->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => 'Sanción por conducta'])->assertOk();

        $this->get(route('jugadores.gestion', $jugador))
            ->assertOk()
            ->assertSee('Cambiar estado')
            ->assertSee('Historial de estados')
            ->assertSee('Activo → Suspendido')
            ->assertSee('Motivo: Sanción por conducta')
            ->assertSee($secretario->name.' · 09/11/2026 19:29');
    }

    public function test_formulario_de_cambio_de_estado_redirige_y_muestra_errores_en_espanol(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo');
        $this->actingAs($secretario);

        $this->followingRedirects()->from(route('jugadores.gestion', $jugador))
            ->put(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => ''])
            ->assertOk()
            ->assertSee('El motivo del cambio de estado es obligatorio.');

        $this->from(route('jugadores.gestion', $jugador))
            ->put(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => 'Sanción de dos partidos'])
            ->assertRedirect(route('jugadores.gestion', $jugador))
            ->assertSessionHas('success', 'El estado del jugador fue actualizado.');

        $this->assertSame('Suspendido', $jugador->fresh()->estado_elegibilidad);
    }

    // --- Autorización: 403 en español para quien no es Secretario ---

    public function test_quien_no_es_secretario_recibe_403_en_espanol(): void
    {
        [$jugador, $delegado] = $this->crearJugadorYDelegado('Activo');
        $otroClub = Club::query()->create(['nombre' => 'Club ajeno estado '.str()->random(6)]);
        $usuarios = [
            $delegado,
            $this->crearUsuarioConRol('Delegado del Club', $otroClub),
            $this->crearUsuarioConRol('Jugador', null, $jugador->persona_id),
            $this->crearUsuarioConRol('Jugador'),
            $this->crearUsuarioConRol('Tesorero'),
            $this->crearUsuarioConRol('Tribunal de Penas'),
        ];

        foreach ($usuarios as $usuario) {
            $this->actingAs($usuario)->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => 'No debe autorizarse'])
                ->assertForbidden()
                ->assertJsonPath('mensaje', 'Solo el Secretario puede cambiar el estado de un jugador.');
        }

        $this->actingAs($delegado)->get(route('jugadores.gestion', $jugador))->assertOk()->assertSee('Historial de estados')->assertDontSee('Cambiar estado');
        $this->assertSame('Activo', $jugador->fresh()->estado_elegibilidad);
        $this->assertSame(0, $jugador->historialEstados()->count());
    }

    public function test_delegado_no_puede_consultar_estado_de_jugador_de_otro_club(): void
    {
        $jugador = $this->crearJugador('Activo');
        $otroClub = Club::query()->create(['nombre' => 'Club ajeno '.str()->random(6)]);

        $this->actingAs($this->crearUsuarioConRol('Delegado del Club', $otroClub))->get(route('jugadores.gestion', $jugador))->assertForbidden();
    }

    public function test_administrador_general_puede_cambiar_el_estado(): void
    {
        $administrador = $this->crearUsuarioConRol('Administrador General');
        $jugador = $this->crearJugador('Activo');

        $this->actingAs($administrador)->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Inelegible', 'motivo' => 'Categoría no corresponde a su edad'])->assertOk();

        $this->assertDatabaseHas('historial_estados_jugador', ['jugador_id' => $jugador->id, 'usuario_id' => $administrador->id, 'estado_nuevo' => 'Inelegible']);
    }

    // --- Validación en español ---

    public function test_rechaza_estado_no_oficial_motivo_invalido_y_cambio_repetido(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo');
        $this->actingAs($secretario);

        $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Transferido', 'motivo' => 'Estado que no existe'])
            ->assertUnprocessable()->assertJsonValidationErrors(['estado_elegibilidad' => 'El estado seleccionado no está permitido.']);
        $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => ['Suspendido'], 'motivo' => ['texto']])
            ->assertUnprocessable()->assertJsonValidationErrors(['estado_elegibilidad' => 'El estado seleccionado no es válido.', 'motivo' => 'El motivo del cambio de estado no es válido.']);
        $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['motivo' => 'El motivo del cambio de estado es obligatorio.']);
        $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Activo', 'motivo' => 'Sin cambio efectivo'])
            ->assertUnprocessable()->assertJsonValidationErrors(['estado_elegibilidad' => 'El jugador ya tiene el estado seleccionado.']);

        $this->assertSame('Activo', $jugador->fresh()->estado_elegibilidad);
        $this->assertSame(0, $jugador->historialEstados()->count());
    }

    // --- Una sola regla de baja para HU-017 y HU-018 ---

    public function test_la_baja_por_hu_018_usa_la_regla_de_hu_017(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo', '-2 months');
        $inicio = Carbon::parse($jugador->equipo->temporada->fecha_inicio)->format('d/m/Y');
        $this->actingAs($secretario);

        $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Baja', 'motivo' => 'Renuncia por escrito'])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_baja');
        $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => 'Fecha sin baja', 'fecha_baja' => GestionJugadorService::hoy()])
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha_baja' => 'La fecha de baja solo se captura al cambiar al estado Baja.']);
        $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Baja', 'motivo' => 'Renuncia por escrito', 'fecha_baja' => '1990-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha_baja' => 'La fecha de baja no puede ser anterior al inicio de la temporada ('.$inicio.').']);

        $fecha = GestionJugadorService::hoy();
        $this->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Baja', 'motivo' => 'Renuncia por escrito', 'fecha_baja' => $fecha])
            ->assertOk()->assertJsonPath('jugador.estado_elegibilidad', 'Baja');

        $this->assertDatabaseHas('jugadores_equipos', ['id' => $jugador->id, 'estado_elegibilidad' => 'Baja', 'motivo_baja' => 'Renuncia por escrito', 'fecha_baja' => $fecha]);
        $this->assertSame(1, $jugador->historialEstados()->where('estado_nuevo', 'Baja')->count());
    }

    public function test_solo_un_jugador_activo_pasa_a_baja_por_ambos_caminos(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        [$suspendido, $delegado] = $this->crearJugadorYDelegado('Suspendido');
        $fecha = GestionJugadorService::hoy();

        $this->actingAs($secretario)->putJson(route('jugadores.estado.update', $suspendido), ['estado_elegibilidad' => 'Baja', 'motivo' => 'Renuncia', 'fecha_baja' => $fecha])
            ->assertUnprocessable()->assertJsonValidationErrors(['jugador' => 'Solo se puede dar de baja a un jugador activo.']);
        $this->actingAs($delegado)->postJson(route('jugadores.baja', $suspendido), ['motivo' => 'Renuncia', 'fecha_baja' => $fecha])
            ->assertUnprocessable()->assertJsonValidationErrors(['jugador' => 'Solo se puede dar de baja a un jugador activo.']);

        $this->assertSame('Suspendido', $suspendido->fresh()->estado_elegibilidad);
    }

    public function test_baja_es_terminal_y_no_se_puede_reactivar(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Baja');
        $jugador->update(['motivo_baja' => 'Renuncia', 'fecha_baja' => GestionJugadorService::hoy()]);

        $this->actingAs($secretario)->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Activo', 'motivo' => 'Intento de reactivación'])
            ->assertUnprocessable()->assertJsonValidationErrors(['jugador']);

        $this->assertSame('Baja', $jugador->fresh()->estado_elegibilidad);
        $this->get(route('jugadores.gestion', $jugador))->assertOk()->assertDontSee('Cambiar estado');
    }

    public function test_no_se_cambia_el_estado_de_una_temporada_cerrada(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo', '-1 year', 'cerrada');

        $this->actingAs($secretario)->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => 'Sanción'])
            ->assertUnprocessable()->assertJsonValidationErrors('jugador');

        $this->assertSame('Activo', $jugador->fresh()->estado_elegibilidad);
    }

    // --- Integración con HU-017 ---

    public function test_la_baja_y_la_transferencia_de_hu_017_quedan_en_el_historial(): void
    {
        [$jugador, $delegado] = $this->crearJugadorYDelegado('Activo');
        $this->actingAs($delegado)->postJson(route('jugadores.baja', $jugador), ['motivo' => 'Cambio de ciudad', 'fecha_baja' => GestionJugadorService::hoy()])->assertOk();
        $this->assertDatabaseHas('historial_estados_jugador', ['jugador_id' => $jugador->id, 'estado_anterior' => 'Activo', 'estado_nuevo' => 'Baja', 'motivo' => 'Cambio de ciudad', 'usuario_id' => $delegado->id]);

        $temporada = $this->temporada();
        $clubDestino = Club::query()->create(['nombre' => 'Club destino estado '.str()->random(6)]);
        $origen = $this->crearEquipo($jugador->equipo->club, $temporada, 'Origen')->jugadores()->create(['persona_id' => Persona::factory()->create()->id, 'estado_elegibilidad' => 'Activo']);
        $administrador = $this->crearUsuarioConRol('Administrador General');
        $this->actingAs($administrador)->postJson(route('jugadores.transferir', $origen), ['equipo_id' => $this->crearEquipo($clubDestino, $temporada, 'Destino')->id])->assertCreated();

        $this->assertDatabaseHas('historial_estados_jugador', ['jugador_id' => $origen->id, 'estado_nuevo' => 'Baja', 'motivo' => 'Transferencia a '.$clubDestino->nombre, 'usuario_id' => $administrador->id]);
    }

    public function test_vender_un_club_con_historial_de_estados_conserva_el_historial(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        [$jugador] = $this->crearJugadorYDelegado('Activo');
        $this->actingAs($secretario)->putJson(route('jugadores.estado.update', $jugador), ['estado_elegibilidad' => 'Suspendido', 'motivo' => 'Sanción'])->assertOk();
        $cambio = $jugador->historialEstados()->sole();

        app(VentaClubService::class)->vender($jugador->equipo->club, $this->crearUsuarioConRol('Presidente del Club')->id, '2026-11-01');

        $this->assertNull(Jugador::query()->find($jugador->id));
        $cambio->refresh();
        $this->assertNull($cambio->jugador_id);
        $this->assertSame($jugador->persona_id, (int) $cambio->persona_id);
    }

    public function test_si_falla_la_auditoria_no_cambia_el_estado_ni_el_historial(): void
    {
        $secretario = $this->crearUsuarioConRol('Secretario');
        $jugador = $this->crearJugador('Activo');
        $auditoria = Mockery::mock(AuditService::class)->makePartial();
        $auditoria->shouldReceive('registrar')->andThrow(new RuntimeException('Fallo inducido en la auditoría.'));
        $this->app->instance(AuditService::class, $auditoria);

        try {
            app(EstadoJugadorService::class)->cambiar($jugador, 'Suspendido', 'Sanción', $secretario);
            $this->fail('El cambio debía fallar junto con la auditoría.');
        } catch (RuntimeException $excepcion) {
            $this->assertSame('Fallo inducido en la auditoría.', $excepcion->getMessage());
        }

        $this->assertSame('Activo', $jugador->fresh()->estado_elegibilidad);
        $this->assertSame(0, $jugador->historialEstados()->count());
    }

    private function temporada(string $inicio = '-3 months', string $estado = 'activa'): Temporada
    {
        return Temporada::factory()->{$estado}()->create(['fecha_inicio' => now(GestionJugadorService::ZONA_HORARIA)->modify($inicio)->toDateString()]);
    }

    private function crearJugador(string $estado, string $inicio = '-3 months', string $estadoTemporada = 'activa'): Jugador
    {
        $club = Club::query()->create(['nombre' => 'Club estado '.str()->random(6)]);
        $equipo = $this->crearEquipo($club, $this->temporada($inicio, $estadoTemporada), 'Equipo estado');

        return $equipo->jugadores()->create(['persona_id' => Persona::factory()->create()->id, 'estado_elegibilidad' => $estado]);
    }

    /** @return array{Jugador, User} */
    private function crearJugadorYDelegado(string $estado): array
    {
        $club = Club::query()->create(['nombre' => 'Club autorizado '.str()->random(6)]);
        $equipo = $this->crearEquipo($club, $this->temporada(), 'Equipo autorizado');
        $jugador = $equipo->jugadores()->create(['persona_id' => Persona::factory()->create()->id, 'estado_elegibilidad' => $estado]);

        return [$jugador, $this->crearUsuarioConRol('Delegado del Club', $club)];
    }

    private function crearUsuarioConRol(string $nombreRol, ?Club $club = null, ?int $personaId = null): User
    {
        $usuario = User::query()->create([
            'name' => 'Usuario estado '.str()->random(6),
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            // La columna usuarios.rol es un enum: los roles de club se guardan como «Directivo Club».
            'rol' => in_array($nombreRol, ['Delegado del Club', 'Presidente del Club'], true) ? 'Directivo Club' : $nombreRol,
            'estatus' => 'activo',
            'club_id' => $club?->id,
        ]);
        if ($personaId !== null) {
            $usuario->forceFill(['persona_id' => $personaId])->save();
        }
        $usuario->assignRole(Role::query()->firstOrCreate(['name' => $nombreRol, 'guard_name' => 'web']));

        return $usuario;
    }

    private function crearEquipo(Club $club, Temporada $temporada, string $nombre): Equipo
    {
        $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id, 'jerarquia_salto' => 1]);
        $grupo = Grupo::factory()->create(['temporada_id' => $temporada->id, 'categoria_id' => $categoria->id]);

        return $club->equipos()->create(['temporada_id' => $temporada->id, 'grupo_id' => $grupo->id, 'nombre' => $nombre.' '.str()->random(4)]);
    }
}
