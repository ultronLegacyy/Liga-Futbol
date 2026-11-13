<?php

namespace Tests\Feature;

use App\Models\Arbitro;
use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\Categoria;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\ReprogramacionPartido;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas completas para HU-029 (SCRUM-44):
 * Gestionar los estados y la reprogramación de partidos (SCRUM-195, SCRUM-196, SCRUM-197, SCRUM-198).
 */
class PartidoEstadosYReprogramacionTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $programador;

    private User $tesorero;

    private Temporada $temporada;

    private Grupo $grupo;

    private Jornada $jornada;

    private Cancha $cancha1;

    private Cancha $cancha2;

    private Arbitro $arbitro1;

    private Arbitro $arbitro2;

    private Equipo $local;

    private Equipo $visitante;

    private int $clubIndex = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_estados@liga.com', 'Administrador General');
        $this->programador = $this->crearUsuario('programador_estados@liga.com', 'Programador de Partidos');
        $this->tesorero = $this->crearUsuario('tesorero_estados@liga.com', 'Tesorero');

        $this->temporada = Temporada::factory()->activa()->create();
        $categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create(['categoria_id' => $categoria->id]);

        $this->jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-11-22',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $this->cancha1 = Cancha::factory()->create([
            'nombre' => 'Cancha Principal',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 4,
        ]);

        $this->cancha2 = Cancha::factory()->create([
            'nombre' => 'Cancha Alterna',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 4,
        ]);

        $this->arbitro1 = $this->crearArbitro('ARBI900101HDFRRN01');
        $this->arbitro2 = $this->crearArbitro('ARBI900101HDFRRN02');

        $this->local = $this->crearEquipo('Tigres Dorados');
        $this->visitante = $this->crearEquipo('Leones Negros');
    }

    public function test_transicion_valida_de_estados_partido(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // borrador -> programado
        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'estado' => Partido::ESTADO_PROGRAMADO,
            ])
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $this->assertSame(Partido::ESTADO_PROGRAMADO, $partido->fresh()->estado);

        // programado -> confirmado
        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'estado' => Partido::ESTADO_CONFIRMADO,
            ])
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $this->assertSame(Partido::ESTADO_CONFIRMADO, $partido->fresh()->estado);

        // confirmado -> en juego
        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'estado' => Partido::ESTADO_EN_JUEGO,
            ])
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $this->assertSame(Partido::ESTADO_EN_JUEGO, $partido->fresh()->estado);

        // en juego -> finalizado
        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'estado' => Partido::ESTADO_FINALIZADO,
            ])
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $this->assertSame(Partido::ESTADO_FINALIZADO, $partido->fresh()->estado);
    }

    public function test_transicion_a_cancelado_o_suspendido_requiere_motivo_obligatorio(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        // Falla si no se envía motivo para cancelación
        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'estado' => Partido::ESTADO_CANCELADO,
                'motivo' => '',
            ])
            ->assertSessionHasErrors(['motivo']);

        $this->assertSame(Partido::ESTADO_PROGRAMADO, $partido->fresh()->estado);

        // Pasa si se incluye motivo válido
        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'estado' => Partido::ESTADO_CANCELADO,
                'motivo' => 'Fuerza mayor por tormenta eléctrica en la zona.',
            ])
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $this->assertSame(Partido::ESTADO_CANCELADO, $partido->fresh()->estado);
    }

    public function test_partido_finalizado_es_terminal_y_rechaza_transiciones(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_FINALIZADO,
        ]);

        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'estado' => Partido::ESTADO_PROGRAMADO,
            ])
            ->assertForbidden();

        $this->assertSame(Partido::ESTADO_FINALIZADO, $partido->fresh()->estado);
    }

    public function test_reprogramar_partido_con_motivo_y_disponibilidad(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $payload = [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '16:00',
            'cancha_id' => $this->cancha2->id,
            'arbitro_id' => $this->arbitro2->id,
            'estado' => Partido::ESTADO_REPROGRAMADO,
            'motivo' => 'Reprogramación solicitada por mutuo acuerdo entre capitanes debido a compromisos escolares.',
        ];

        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partido), $payload)
            ->assertRedirect(route('jornadas.show', $this->jornada))
            ->assertSessionHas('success');

        $partido->refresh();
        $this->assertSame('2026-11-21', $partido->fecha_partido?->toDateString());
        $this->assertSame('16:00:00', $partido->hora_partido);
        $this->assertSame($this->cancha2->id, $partido->cancha_id);
        $this->assertSame($this->arbitro2->id, $partido->arbitro_id);
        $this->assertSame(Partido::ESTADO_REPROGRAMADO, $partido->estado);

        // Verificar auditoría en bitácora
        $this->assertDatabaseHas('reprogramaciones_partidos', [
            'partido_id' => $partido->id,
            'usuario_id' => $this->programador->id,
            'estado_anterior' => Partido::ESTADO_PROGRAMADO,
            'estado_nuevo' => Partido::ESTADO_REPROGRAMADO,
            'cancha_anterior_id' => $this->cancha1->id,
            'cancha_nueva_id' => $this->cancha2->id,
            'fecha_anterior' => '2026-11-20',
            'fecha_nueva' => '2026-11-21',
            'hora_anterior' => '10:00:00',
            'hora_nueva' => '16:00:00',
            'motivo' => $payload['motivo'],
        ]);
    }

    public function test_reprogramacion_falla_si_motivo_es_inferior_a_10_caracteres(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $payload = [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '16:00',
            'cancha_id' => $this->cancha2->id,
            'arbitro_id' => $this->arbitro2->id,
            'motivo' => 'Lluvia', // Demasiado corto (< 10 caracteres)
        ];

        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partido), $payload)
            ->assertSessionHasErrors(['motivo']);

        $this->assertSame('2026-11-20', $partido->fresh()->fecha_partido?->toDateString());
    }

    public function test_reprogramacion_rechaza_cancha_bloqueada(): void
    {
        BloqueoCancha::create([
            'cancha_id' => $this->cancha2->id,
            'fecha_inicio' => '2026-11-21 00:00:00',
            'fecha_fin' => '2026-11-21 23:59:59',
            'motivo' => 'Mantenimiento mayor de pasto sintético',
        ]);

        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $payload = [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '11:00',
            'cancha_id' => $this->cancha2->id,
            'motivo' => 'Reprogramación para horario matutino de fin de semana.',
        ];

        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partido), $payload)
            ->assertSessionHasErrors(['cancha_id']);
    }

    public function test_formulario_de_reprogramacion_carga_correctamente(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $this->actingAs($this->programador)
            ->get(route('partidos.reprogramar', $partido))
            ->assertOk()
            ->assertSee('Reprogramar Partido')
            ->assertSee('Tigres Dorados')
            ->assertSee('Leones Negros');
    }

    public function test_vista_historial_muestra_bitacora_de_reprogramacion(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        ReprogramacionPartido::create([
            'partido_id' => $partido->id,
            'usuario_id' => $this->programador->id,
            'estado_anterior' => Partido::ESTADO_PROGRAMADO,
            'estado_nuevo' => Partido::ESTADO_REPROGRAMADO,
            'cancha_anterior_id' => $this->cancha1->id,
            'cancha_nueva_id' => $this->cancha2->id,
            'fecha_anterior' => '2026-11-20',
            'fecha_nueva' => '2026-11-21',
            'hora_anterior' => '10:00:00',
            'hora_nueva' => '12:00:00',
            'motivo' => 'Ajuste de rol por contingencia de transporte',
        ]);

        $this->actingAs($this->programador)
            ->get(route('partidos.historial', $partido))
            ->assertOk()
            ->assertSee('Historial de Partido')
            ->assertSee('Ajuste de rol por contingencia de transporte')
            ->assertSee($this->programador->name);
    }

    public function test_usuario_sin_permiso_no_puede_cambiar_estado_ni_reprogramar(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $this->actingAs($this->tesorero)
            ->patch(route('partidos.estado.update', $partido), [
                'estado' => Partido::ESTADO_CONFIRMADO,
            ])
            ->assertForbidden();

        $this->actingAs($this->tesorero)
            ->post(route('partidos.reprogramar.store', $partido), [
                'fecha_partido' => '2026-11-21',
                'hora_partido' => '12:00',
                'motivo' => 'Intento de reprogramación no autorizado.',
            ])
            ->assertForbidden();
    }

    public function test_partido_cancelado_es_terminal_y_rechaza_reprogramacion(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_CANCELADO,
        ]);

        $payload = [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '16:00',
            'cancha_id' => $this->cancha1->id,
            'motivo' => 'Intento de revivir y reprogramar un partido cancelado.',
        ];

        // Programador de Partidos es bloqueado por política
        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partido), $payload)
            ->assertForbidden();

        // Administrador General también es bloqueado por política
        $this->actingAs($this->admin)
            ->post(route('partidos.reprogramar.store', $partido), $payload)
            ->assertForbidden();

        $this->assertSame(Partido::ESTADO_CANCELADO, $partido->fresh()->estado);
    }

    public function test_partido_finalizado_rechaza_reprogramacion_incluso_por_administrador(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_FINALIZADO,
        ]);

        $payload = [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '16:00',
            'cancha_id' => $this->cancha1->id,
            'motivo' => 'Intento de reprogramación sobre un partido ya finalizado.',
        ];

        $this->actingAs($this->admin)
            ->post(route('partidos.reprogramar.store', $partido), $payload)
            ->assertForbidden();

        $this->assertSame(Partido::ESTADO_FINALIZADO, $partido->fresh()->estado);
    }

    public function test_partidos_en_borrador_o_en_juego_rechazan_reprogramacion(): void
    {
        $partidoBorrador = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $payload = [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '16:00',
            'cancha_id' => $this->cancha1->id,
            'motivo' => 'Intento de reprogramar un partido que aún está en borrador.',
        ];

        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partidoBorrador), $payload)
            ->assertForbidden();

        $partidoEnJuego = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_EN_JUEGO,
        ]);

        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partidoEnJuego), $payload)
            ->assertForbidden();
    }

    public function test_reprogramar_partido_ya_reprogramado_mantiene_o_transiciona_estado_valido(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_REPROGRAMADO,
        ]);

        // 1. Reprogramar manteniendo el estado reprogramado con nueva fecha y hora
        $payloadMantener = [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '15:00',
            'cancha_id' => $this->cancha2->id,
            'estado' => Partido::ESTADO_REPROGRAMADO,
            'motivo' => 'Ajuste de fecha manteniendo el estado reprogramado pendiente de confirmación.',
        ];

        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partido), $payloadMantener)
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $partido->refresh();
        $this->assertSame(Partido::ESTADO_REPROGRAMADO, $partido->estado);
        $this->assertSame('2026-11-21', $partido->fecha_partido?->toDateString());
        $this->assertSame('15:00:00', $partido->hora_partido);

        // 2. Reprogramar y transicionar a confirmado
        $payloadConfirmar = [
            'fecha_partido' => '2026-11-22',
            'hora_partido' => '11:00',
            'cancha_id' => $this->cancha1->id,
            'estado' => Partido::ESTADO_CONFIRMADO,
            'motivo' => 'Confirmación definitiva de nueva fecha y horario consensuado.',
        ];

        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partido), $payloadConfirmar)
            ->assertRedirect(route('jornadas.show', $this->jornada));

        $partido->refresh();
        $this->assertSame(Partido::ESTADO_CONFIRMADO, $partido->estado);
        $this->assertSame('2026-11-22', $partido->fecha_partido?->toDateString());
    }

    public function test_reprogramacion_rechaza_transicion_invalida_segun_maquina_de_estados(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha1->id,
            'arbitro_id' => $this->arbitro1->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        // 'programado' no puede transicionar a 'programado' (debe pasar a reprogramado o confirmado)
        $payloadInvalido = [
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '16:00',
            'cancha_id' => $this->cancha2->id,
            'estado' => Partido::ESTADO_PROGRAMADO,
            'motivo' => 'Intento de transición idéntica que no pasa por reprogramado.',
        ];

        $this->actingAs($this->programador)
            ->post(route('partidos.reprogramar.store', $partido), $payloadInvalido)
            ->assertSessionHasErrors(['estado']);

        $this->assertSame(Partido::ESTADO_PROGRAMADO, $partido->fresh()->estado);
    }

    private function crearEquipo(string $nombre, ?Grupo $grupo = null): Equipo
    {
        $this->clubIndex++;
        $club = Club::create(['nombre' => 'Club '.$this->clubIndex.' '.$nombre]);

        return Equipo::create([
            'club_id' => $club->id,
            'grupo_id' => $grupo ? $grupo->id : $this->grupo->id,
            'nombre' => $nombre,
        ]);
    }

    private function crearArbitro(string $curp, ?Temporada $temporada = null): Arbitro
    {
        $rol = app(\App\Services\IdentidadPersonaService::class)->asignarRol([
            'nombre' => 'Árbitro',
            'apellidos' => 'Prueba '.$curp,
            'fecha_nacimiento' => '1988-03-12',
            'curp' => $curp,
        ], 'arbitro', $temporada ?? $this->temporada);

        return Arbitro::create(['persona_id' => $rol->persona_id, 'estado' => 'Activo']);
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
