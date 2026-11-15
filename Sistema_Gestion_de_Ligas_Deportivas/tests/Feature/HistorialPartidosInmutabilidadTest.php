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
use App\Models\Partido;
use App\Models\ReprogramacionPartido;
use App\Models\Temporada;
use App\Models\User;
use App\Services\ProgramacionJornadaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pruebas completas para HU-031 (SCRUM-46):
 * Conservar el historial de partidos con resultados e inmutabilidad (SCRUM-203, SCRUM-204, SCRUM-205).
 */
class HistorialPartidosInmutabilidadTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $programador;

    private Temporada $temporada;

    private Grupo $grupo;

    private Jornada $jornada;

    private Cancha $cancha;

    private Arbitro $arbitro;

    private Equipo $local;

    private Equipo $visitante;

    private int $clubIndex = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_hist@liga.com', 'Administrador General');
        $this->programador = $this->crearUsuario('prog_hist@liga.com', 'Programador de Partidos');

        $this->temporada = Temporada::factory()->activa()->create([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
        ]);
        $categoria = Categoria::factory()->create(['temporada_id' => $this->temporada->id]);
        $this->grupo = Grupo::factory()->create(['categoria_id' => $categoria->id]);

        $this->jornada = Jornada::create([
            'grupo_id' => $this->grupo->id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-11-20',
            'fecha_fin' => '2026-11-22',
            'estado' => Jornada::ESTADO_BORRADOR,
        ]);

        $this->cancha = Cancha::factory()->create([
            'nombre' => 'Cancha San Jerónimo',
            'estado' => 'Disponible',
            'capacidad_partidos_consecutivos' => 4,
        ]);

        $this->arbitro = $this->crearArbitro('ARBI920101HDFRRN01');

        $this->local = $this->crearEquipo('Atlético San Luis');
        $this->visitante = $this->crearEquipo('Mineros');
    }

    public function test_partido_en_borrador_sin_historial_si_se_puede_eliminar(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        $this->assertTrue($partido->esEliminable());

        $this->actingAs($this->programador)
            ->delete(route('partidos.destroy', $partido))
            ->assertRedirect(route('jornadas.show', $this->jornada))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('partidos', ['id' => $partido->id]);
    }

    public function test_partido_programado_o_jugado_no_puede_eliminarse_ni_por_administrador_general(): void
    {
        // Regla estricta SCRUM-203: "ningún usuario, ni por petición directa, elimina partidos jugados o programados"
        $partidoProgramado = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $this->assertFalse($partidoProgramado->esEliminable());

        // Intentar eliminar como Administrador General
        $this->actingAs($this->admin)
            ->delete(route('partidos.destroy', $partidoProgramado))
            ->assertForbidden();

        $this->assertDatabaseHas('partidos', ['id' => $partidoProgramado->id]);

        // Partido finalizado con resultados
        $partidoFinalizado = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '12:00:00',
            'estado' => Partido::ESTADO_FINALIZADO,
            'goles_local' => 2,
            'goles_visitante' => 1,
        ]);

        $this->assertFalse($partidoFinalizado->esEliminable());

        $this->actingAs($this->admin)
            ->delete(route('partidos.destroy', $partidoFinalizado))
            ->assertForbidden();

        $this->assertDatabaseHas('partidos', ['id' => $partidoFinalizado->id]);
    }

    public function test_partido_con_cedula_arbitral_o_bitacora_queda_bloqueado_contra_eliminacion(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        // Agregar cédula arbitral
        CedulaArbitral::create([
            'partido_id' => $partido->id,
            'estado' => 'cerrada',
            'goles_local' => 3,
            'goles_visitante' => 2,
        ]);

        $this->assertFalse($partido->fresh()->esEliminable());

        $this->actingAs($this->programador)
            ->delete(route('partidos.destroy', $partido))
            ->assertRedirect()
            ->assertSessionHasErrors(['estado' => 'No se puede eliminar un partido cuya cédula arbitral ya tiene datos capturados.']);

        $this->assertDatabaseHas('partidos', ['id' => $partido->id]);
    }

    public function test_partido_con_reprogramaciones_anteriores_no_puede_eliminarse(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_BORRADOR,
        ]);

        ReprogramacionPartido::create([
            'partido_id' => $partido->id,
            'usuario_id' => $this->admin->id,
            'estado_anterior' => Partido::ESTADO_BORRADOR,
            'estado_nuevo' => Partido::ESTADO_BORRADOR,
            'cancha_anterior_id' => $this->cancha->id,
            'cancha_nueva_id' => $this->cancha->id,
            'fecha_anterior' => '2026-11-20',
            'fecha_nueva' => '2026-11-20',
            'hora_anterior' => '10:00:00',
            'hora_nueva' => '12:00:00',
            'motivo' => 'Ajuste de horario previo',
        ]);

        $this->assertFalse($partido->fresh()->esEliminable());

        $this->actingAs($this->programador)
            ->delete(route('partidos.destroy', $partido))
            ->assertForbidden();

        $this->assertDatabaseHas('partidos', ['id' => $partido->id]);
    }

    public function test_servicio_eliminar_partido_lanza_excepcion_si_no_es_eliminable(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_FINALIZADO,
        ]);

        $service = app(ProgramacionJornadaService::class);

        $this->expectException(ValidationException::class);
        $service->eliminarPartido($partido);
    }

    public function test_vista_jornada_muestra_historial_protegido_en_vez_de_eliminar(): void
    {
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        $this->actingAs($this->admin)
            ->get(route('jornadas.show', $this->jornada))
            ->assertOk()
            ->assertSee('Historial protegido');
    }

    public function test_actualizar_partido_no_permite_saltarse_inmutabilidad_cambiando_estado_a_borrador(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        // Intentar pasar a borrador vía PUT para luego intentar borrarlo (Obligatorio 1)
        $this->actingAs($this->programador)
            ->put(route('partidos.update', $partido), [
                'equipo_local_id' => $this->local->id,
                'equipo_visitante_id' => $this->visitante->id,
                'cancha_id' => $this->cancha->id,
                'fecha_partido' => '2026-11-20',
                'hora_partido' => '10:00',
                'estado' => Partido::ESTADO_BORRADOR,
            ]);

        // El estado debe permanecer como programado y no puede ser borrado
        $this->assertSame(Partido::ESTADO_PROGRAMADO, $partido->fresh()->estado);
        $this->assertFalse($partido->fresh()->esEliminable());

        // El borrado debe ser rechazado
        $this->actingAs($this->programador)
            ->delete(route('partidos.destroy', $partido))
            ->assertForbidden();
    }

    public function test_eliminar_jornada_bloquea_si_contiene_partidos_protegidos(): void
    {
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        // Obligatorio 2: DELETE /jornadas/{id} debe ser bloqueado por política
        $this->actingAs($this->programador)
            ->delete(route('jornadas.destroy', $this->jornada))
            ->assertForbidden();

        $this->assertDatabaseHas('jornadas', ['id' => $this->jornada->id]);
    }

    public function test_reemplazar_rol_bloquea_si_contiene_partidos_protegidos(): void
    {
        Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_REPROGRAMADO,
        ]);

        $generador = app(\App\Services\GeneradorEnfrentamientosService::class);
        $propuesta = $generador->generarPropuestaRol($this->grupo);

        $this->expectException(ValidationException::class);
        $generador->guardarRolGenerado($this->grupo, $propuesta, true);
    }

    public function test_partido_programado_con_cedula_vacia_no_puede_eliminarse_y_da_403(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        // Cédula abierta pero sin capturar aún (vacía, sin datos de captura)
        CedulaArbitral::create([
            'partido_id' => $partido->id,
            'estado' => 'abierta',
        ]);

        $this->assertFalse($partido->fresh()->esEliminable());

        // Debe retornar 403 Forbidden por la política de inmutabilidad HU-031 / D-040
        $this->actingAs($this->programador)
            ->delete(route('partidos.destroy', $partido))
            ->assertForbidden();

        $this->assertDatabaseHas('partidos', ['id' => $partido->id]);
    }

    public function test_administrador_general_no_puede_modificar_ni_reprogramar_partido_con_cedula_cerrada(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        CedulaArbitral::create([
            'partido_id' => $partido->id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => 2,
            'goles_visitante' => 0,
        ]);

        $this->actingAs($this->admin)
            ->put(route('partidos.update', $partido), [
                'equipo_local_id' => $this->local->id,
                'equipo_visitante_id' => $this->visitante->id,
                'cancha_id' => $this->cancha->id,
                'fecha_partido' => '2026-11-21',
                'hora_partido' => '12:00',
                'motivo' => 'Intento de alterar partido con cédula cerrada.',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('partidos.reprogramar.store', $partido), [
                'cancha_id' => $this->cancha->id,
                'fecha_partido' => '2026-11-21',
                'hora_partido' => '12:00',
                'motivo' => 'Intento de reprogramar partido con cédula cerrada.',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('partidos.estado.update', $partido), [
                'nuevo_estado' => Partido::ESTADO_CANCELADO,
                'motivo' => 'Intento de cancelar partido con cédula cerrada.',
            ])
            ->assertForbidden();

        // Tampoco un partido en juego con cédula cerrada permite transiciones manuales
        $partidoEnJuego = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-21',
            'hora_partido' => '14:00:00',
            'estado' => Partido::ESTADO_EN_JUEGO,
        ]);

        CedulaArbitral::create([
            'partido_id' => $partidoEnJuego->id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => 1,
            'goles_visitante' => 1,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('partidos.estado.update', $partidoEnJuego), [
                'nuevo_estado' => Partido::ESTADO_FINALIZADO,
                'motivo' => 'Intento de finalizar manualmente un partido con cédula cerrada.',
            ])
            ->assertForbidden();

        $this->assertSame(Partido::ESTADO_EN_JUEGO, $partidoEnJuego->fresh()->estado);
    }

    public function test_no_se_puede_finalizar_manualmente_un_partido_que_tiene_cedula_abierta_ni_cerrada(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-22',
            'hora_partido' => '16:00:00',
            'estado' => Partido::ESTADO_EN_JUEGO,
        ]);

        CedulaArbitral::create([
            'partido_id' => $partido->id,
            'estado' => CedulaArbitral::ABIERTA,
        ]);

        // Un partido con cédula registrada no puede pasarse a finalizado manualmente ni en vista ni vía PATCH
        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'nuevo_estado' => Partido::ESTADO_FINALIZADO,
                'motivo' => 'Intento manual de finalizar partido teniendo cédula abierta.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['nuevo_estado']);

        $this->assertSame(Partido::ESTADO_EN_JUEGO, $partido->fresh()->estado);

        // La vista de detalle tampoco ofrece 'finalizado' en las opciones mientras tenga cédula
        $this->actingAs($this->programador)
            ->get(route('partidos.show', $partido))
            ->assertOk()
            ->assertDontSee('<option value="finalizado">', false);

        // Sí permite suspender el encuentro en curso por causa justificada
        $this->actingAs($this->programador)
            ->patch(route('partidos.estado.update', $partido), [
                'nuevo_estado' => Partido::ESTADO_SUSPENDIDO,
                'motivo' => 'Tormenta eléctrica obliga a suspender el encuentro en curso.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(Partido::ESTADO_SUSPENDIDO, $partido->fresh()->estado);
    }

    public function test_no_se_puede_cancelar_partido_con_cedula_capturada_ni_alterar_bitacora(): void
    {
        $partido = Partido::create([
            'jornada_id' => $this->jornada->id,
            'equipo_local_id' => $this->local->id,
            'equipo_visitante_id' => $this->visitante->id,
            'cancha_id' => $this->cancha->id,
            'arbitro_id' => $this->arbitro->id,
            'fecha_partido' => '2026-11-20',
            'hora_partido' => '10:00:00',
            'estado' => Partido::ESTADO_PROGRAMADO,
        ]);

        CedulaArbitral::create([
            'partido_id' => $partido->id,
            'estado' => CedulaArbitral::ABIERTA,
            'goles_local' => 2,
            'goles_visitante' => 1,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('partidos.estado.update', $partido), [
                'nuevo_estado' => Partido::ESTADO_CANCELADO,
                'motivo' => 'Intento de cancelar un partido con marcador capturado.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['nuevo_estado']);

        $this->assertSame(Partido::ESTADO_PROGRAMADO, $partido->fresh()->estado);

        $bitacora = ReprogramacionPartido::create([
            'partido_id' => $partido->id,
            'usuario_id' => $this->admin->id,
            'estado_anterior' => Partido::ESTADO_BORRADOR,
            'estado_nuevo' => Partido::ESTADO_PROGRAMADO,
            'cancha_anterior_id' => $this->cancha->id,
            'cancha_nueva_id' => $this->cancha->id,
            'fecha_anterior' => '2026-11-20',
            'fecha_nueva' => '2026-11-20',
            'hora_anterior' => '10:00:00',
            'hora_nueva' => '10:00:00',
            'motivo' => 'Registro inicial inmutable en bitácora oficial.',
        ]);

        try {
            $bitacora->update(['motivo' => 'Alteración indebida']);
            $this->fail('Se esperaba LogicException al intentar modificar bitácora.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('inmutable', $e->getMessage());
        }

        $this->expectException(\LogicException::class);
        $bitacora->delete();
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
