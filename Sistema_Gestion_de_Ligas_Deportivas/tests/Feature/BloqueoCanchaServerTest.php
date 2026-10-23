<?php

namespace Tests\Feature;

use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

// SCRUM-139 (S2-T26): Disponibilidad y bloqueos de canchas, parte servidor (HU-024, RF-05.2)
class BloqueoCanchaServerTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $usuarioSinPermiso;

    private Cancha $cancha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_bloqueos@liga.com', 'Administrador General');
        $this->usuarioSinPermiso = $this->crearUsuario('tesorero_bloqueos@liga.com', 'Tesorero');

        $this->cancha = Cancha::create([
            'nombre' => 'Cancha 12 U.D.C.',
            'direccion_ubicacion' => 'Unidad Deportiva Cuauhtemoc, Morelia',
            'capacidad_partidos_consecutivos' => 2,
            'estado' => 'Disponible',
        ]);
    }

    // 1. Relacion Eloquent Cancha -> Bloqueos
    public function test_cancha_posee_relacion_has_many_con_bloqueos_cancha(): void
    {
        $bloqueo = BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::parse('2026-11-01 08:00:00'),
            'fecha_fin' => Carbon::parse('2026-11-01 14:00:00'),
            'motivo' => 'Mantenimiento preventivo de césped',
        ]);

        $this->assertTrue($this->cancha->bloqueos()->where('id', $bloqueo->id)->exists());
        $this->assertInstanceOf(Cancha::class, $bloqueo->cancha);
        $this->assertSame($this->cancha->id, $bloqueo->cancha->id);
    }

    // 2. Metodo estaDisponible cuando no hay bloqueos (CA1)
    public function test_cancha_esta_disponible_si_no_tiene_bloqueos_en_el_horario(): void
    {
        $this->assertTrue(
            $this->cancha->estaDisponible('2026-11-01 08:00:00', '2026-11-01 12:00:00')
        );

        $this->assertTrue(
            $this->cancha->estaDisponible('2026-11-01', '08:00:00', '12:00:00')
        );
    }

    // 3. Metodo estaDisponible cuando existe un bloqueo solapado (CA2, CA3)
    public function test_cancha_no_esta_disponible_si_existe_un_bloqueo_en_el_horario(): void
    {
        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::parse('2026-11-01 10:00:00'),
            'fecha_fin' => Carbon::parse('2026-11-01 14:00:00'),
            'motivo' => 'Lluvias torrenciales y campo inundado',
        ]);

        // Solape total / contenido
        $this->assertFalse(
            $this->cancha->estaDisponible('2026-11-01 11:00:00', '2026-11-01 13:00:00')
        );

        // Solape parcial al inicio
        $this->assertFalse(
            $this->cancha->estaDisponible('2026-11-01 09:00:00', '2026-11-01 11:00:00')
        );

        // Solape parcial al fin
        $this->assertFalse(
            $this->cancha->estaDisponible('2026-11-01 13:00:00', '2026-11-01 15:00:00')
        );

        // Sin solape antes
        $this->assertTrue(
            $this->cancha->estaDisponible('2026-11-01 08:00:00', '2026-11-01 10:00:00')
        );

        // Sin solape despues
        $this->assertTrue(
            $this->cancha->estaDisponible('2026-11-01 14:00:00', '2026-11-01 18:00:00')
        );
    }

    // 4. Cancha inactiva nunca esta disponible
    public function test_cancha_inactiva_nunca_esta_disponible(): void
    {
        $this->cancha->update(['estado' => 'Inactiva']);

        $this->assertFalse(
            $this->cancha->estaDisponible('2026-11-01 08:00:00', '2026-11-01 10:00:00')
        );
    }

    // 5. Excepcion si fecha fin es anterior o igual a fecha inicio
    public function test_esta_disponible_lanza_excepcion_si_rango_es_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cancha->estaDisponible('2026-11-01 12:00:00', '2026-11-01 10:00:00');
    }

    // 6. Scope scopeDisponiblesEn para programadores de partidos (HU-025)
    public function test_scope_disponibles_en_filtra_canchas_correctamente(): void
    {
        $cancha2 = Cancha::create([
            'nombre' => 'Cancha 14 U.D.C.',
            'estado' => 'Disponible',
        ]);

        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::parse('2026-11-01 10:00:00'),
            'fecha_fin' => Carbon::parse('2026-11-01 12:00:00'),
            'motivo' => 'Mantenimiento',
        ]);

        $disponibles = Cancha::disponiblesEn('2026-11-01 10:00:00', '2026-11-01 12:00:00')->pluck('id');

        $this->assertFalse($disponibles->contains($this->cancha->id));
        $this->assertTrue($disponibles->contains($cancha2->id));
    }

    // 7. Endpoint GET /canchas/{cancha}/bloqueos
    public function test_usuario_con_permiso_puede_consultar_bloqueos_de_cancha(): void
    {
        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::parse('2026-11-01 10:00:00'),
            'fecha_fin' => Carbon::parse('2026-11-01 12:00:00'),
            'motivo' => 'Bloqueo por torneo infantil',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->getJson(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertStatus(200);
        $respuesta->assertJson([
            'modulo' => 'Bloqueos de Cancha',
            'cancha_id' => $this->cancha->id,
            'nombre_cancha' => $this->cancha->nombre,
        ]);
        $respuesta->assertJsonCount(1, 'bloqueos');
    }

    // 8. Registro exitoso de bloqueo por usuario autorizado (CA1, CA2)
    public function test_administrador_registra_bloqueo_con_motivo_obligatorio(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->cancha), [
                'fecha_inicio' => '2026-11-05 08:00:00',
                'fecha_fin' => '2026-11-05 13:00:00',
                'motivo' => 'Reacondicionamiento de redes y demarcación',
            ]);

        $respuesta->assertStatus(201);
        $respuesta->assertJson([
            'mensaje' => 'Bloqueo registrado correctamente.',
            'bloqueo' => [
                'cancha_id' => $this->cancha->id,
                'motivo' => 'Reacondicionamiento de redes y demarcación',
            ],
        ]);

        $this->assertDatabaseHas('bloqueos_cancha', [
            'cancha_id' => $this->cancha->id,
            'motivo' => 'Reacondicionamiento de redes y demarcación',
        ]);
    }

    // 9. Registro en auditoria inmutable (CA4, RNF-22)
    public function test_registro_de_bloqueo_crea_registro_en_auditoria_con_motivo(): void
    {
        $motivoEsperado = 'Suspension por evento oficial de la Liga';

        $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->cancha), [
                'fecha_inicio' => '2026-11-06 09:00:00',
                'fecha_fin' => '2026-11-06 14:00:00',
                'motivo' => $motivoEsperado,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $this->admin->id,
            'accion' => 'crear',
            'tabla_afectada' => 'bloqueos_cancha',
            'motivo' => $motivoEsperado,
        ]);
    }

    // 10. Prevencion estricta de traslapes en la misma cancha (CA3)
    public function test_rechaza_registro_de_bloqueo_si_se_traslapa_con_otro_existente(): void
    {
        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::parse('2026-11-10 10:00:00'),
            'fecha_fin' => Carbon::parse('2026-11-10 14:00:00'),
            'motivo' => 'Mantenimiento programado inicial',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->cancha), [
                'fecha_inicio' => '2026-11-10 12:00:00',
                'fecha_fin' => '2026-11-10 16:00:00',
                'motivo' => 'Intento de segundo bloqueo solapado',
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['fecha_inicio']);
        $this->assertSame(
            'La cancha ya cuenta con un bloqueo registrado que se traslapa con el horario seleccionado.',
            $respuesta->json('errors.fecha_inicio.0')
        );
    }

    // 11. Se permiten bloqueos en el mismo horario en canchas distintas
    public function test_se_permiten_bloqueos_en_el_mismo_horario_en_diferentes_canchas(): void
    {
        $cancha2 = Cancha::create([
            'nombre' => 'Cancha 2 U.D.C.',
            'estado' => 'Disponible',
        ]);

        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::parse('2026-11-10 10:00:00'),
            'fecha_fin' => Carbon::parse('2026-11-10 14:00:00'),
            'motivo' => 'Bloqueo Cancha 1',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $cancha2), [
                'fecha_inicio' => '2026-11-10 10:00:00',
                'fecha_fin' => '2026-11-10 14:00:00',
                'motivo' => 'Bloqueo Cancha 2 mismo horario',
            ]);

        $respuesta->assertStatus(201);
    }

    // 12. Validacion de fechas: fecha_fin posterior a fecha_inicio
    public function test_validacion_exige_fecha_fin_posterior_a_fecha_inicio(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('canchas.bloqueos.store', $this->cancha), [
                'fecha_inicio' => '2026-11-12 15:00:00',
                'fecha_fin' => '2026-11-12 10:00:00',
                'motivo' => 'Horario invertido erroneo',
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['fecha_fin']);
    }

    // 13. Retiro / cancelacion de bloqueo con registro en auditoria (CA4)
    public function test_liberacion_de_bloqueo_elimina_registro_y_crea_auditoria(): void
    {
        $bloqueo = BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::parse('2026-11-15 08:00:00'),
            'fecha_fin' => Carbon::parse('2026-11-15 12:00:00'),
            'motivo' => 'Bloqueo temporal a liberar',
        ]);

        $motivoLiberacion = 'Se concluyeron las reparaciones antes de tiempo';

        $respuesta = $this->actingAs($this->admin)
            ->deleteJson(route('canchas.bloqueos.destroy', [$this->cancha, $bloqueo]), [
                'motivo' => $motivoLiberacion,
            ]);

        $respuesta->assertStatus(200);
        $respuesta->assertJson([
            'mensaje' => 'Bloqueo de cancha liberado correctamente.',
            'bloqueo_id' => $bloqueo->id,
        ]);

        $this->assertDatabaseMissing('bloqueos_cancha', ['id' => $bloqueo->id]);

        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $this->admin->id,
            'accion' => 'eliminar',
            'tabla_afectada' => 'bloqueos_cancha',
            'registro_id' => $bloqueo->id,
            'motivo' => $motivoLiberacion,
        ]);
    }

    // 14. Rechazo con 403 Forbidden a usuarios sin permiso campos.bloquear (CA4)
    public function test_usuario_sin_permiso_es_rechazado_con_403_al_intentar_bloquear(): void
    {
        $respuesta = $this->actingAs($this->usuarioSinPermiso)
            ->postJson(route('canchas.bloqueos.store', $this->cancha), [
                'fecha_inicio' => '2026-11-20 08:00:00',
                'fecha_fin' => '2026-11-20 12:00:00',
                'motivo' => 'Intento no autorizado',
            ]);

        $respuesta->assertStatus(403);
    }

    // 15. Endpoint de consulta de disponibilidad
    public function test_endpoint_de_disponibilidad_informa_estado_correctamente(): void
    {
        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::parse('2026-11-25 10:00:00'),
            'fecha_fin' => Carbon::parse('2026-11-25 14:00:00'),
            'motivo' => 'Cancha reservada para mantenimiento',
        ]);

        // Consulta en horario bloqueado
        $resBloqueado = $this->actingAs($this->admin)
            ->getJson(route('canchas.disponibilidad', [
                'cancha' => $this->cancha,
                'fecha_inicio' => '2026-11-25 11:00:00',
                'fecha_fin' => '2026-11-25 12:00:00',
            ]));

        $resBloqueado->assertStatus(200);
        $resBloqueado->assertJson([
            'cancha_id' => $this->cancha->id,
            'disponible' => false,
        ]);

        // Consulta en horario libre
        $resLibre = $this->actingAs($this->admin)
            ->getJson(route('canchas.disponibilidad', [
                'cancha' => $this->cancha,
                'fecha_inicio' => '2026-11-25 15:00:00',
                'fecha_fin' => '2026-11-25 18:00:00',
            ]));

        $resLibre->assertStatus(200);
        $resLibre->assertJson([
            'cancha_id' => $this->cancha->id,
            'disponible' => true,
        ]);
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario Test Bloqueos',
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
