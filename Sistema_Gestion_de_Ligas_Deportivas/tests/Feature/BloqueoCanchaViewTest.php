<?php

namespace Tests\Feature;

use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// SCRUM-140 (S2-T27): Vistas de disponibilidad y bloqueos por cancha (HU-024, RF-05.2)
class BloqueoCanchaViewTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $usuarioLectura;

    private User $usuarioSinAcceso;

    private Cancha $cancha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_vistas@liga.com', 'Administrador General');

        // Secretario tiene campos.ver pero no posee campos.bloquear (usuario solo lectura de canchas)
        $this->usuarioLectura = $this->crearUsuario('secretario_vistas@liga.com', 'Secretario');

        // Tesorero esta estrictamente aislado de canchas y modulos deportivos (sin campos.ver)
        $this->usuarioSinAcceso = $this->crearUsuario('tesorero_vistas@liga.com', 'Tesorero');

        $this->cancha = Cancha::create([
            'nombre' => 'Cancha 5 Estadio Venustiano Carranza',
            'direccion_ubicacion' => 'Av. del Estudiante s/n, Morelia',
            'capacidad_partidos_consecutivos' => 3,
            'estado' => 'Disponible',
        ]);
    }

    // 1. Renderizado de vista de bloqueos para usuario autorizado
    public function test_vista_bloqueos_renderiza_correctamente_para_administrador(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertStatus(200);
        $respuesta->assertViewIs('canchas.bloqueos.index');
        $respuesta->assertViewHas('cancha', $this->cancha);
        $respuesta->assertSee('Cancha 5 Estadio Venustiano Carranza');
        $respuesta->assertSee('Av. del Estudiante s/n, Morelia');
        $respuesta->assertSee('3 partidos');
        $respuesta->assertSee('Disponible para Partidos');
        $respuesta->assertSee('Comprobador de Disponibilidad en Tiempo Real');
    }

    // 2. Separacion de bloqueos vigentes e historicos en la vista
    public function test_vista_muestra_bloqueos_vigentes_y_bloqueos_historicos_separados(): void
    {
        $bloqueoFuturo = BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::now()->addDays(2)->setTime(9, 0),
            'fecha_fin' => Carbon::now()->addDays(2)->setTime(13, 0),
            'motivo' => 'Mantenimiento programado de luminarias',
        ]);

        $bloqueoPasado = BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::now()->subDays(5)->setTime(8, 0),
            'fecha_fin' => Carbon::now()->subDays(5)->setTime(12, 0),
            'motivo' => 'Reparacion de redes de porteria antigua',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Mantenimiento programado de luminarias');
        $respuesta->assertSee('Reparacion de redes de porteria antigua');
        $respuesta->assertSee('Programado');
        $respuesta->assertSee('Finalizado');
    }

    // 3. Indicador visual cuando la cancha tiene un bloqueo activo en el momento actual
    public function test_vista_muestra_badge_de_bloqueada_actualmente_si_hay_bloqueo_en_curso(): void
    {
        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::now()->subHour(),
            'fecha_fin' => Carbon::now()->addHours(3),
            'motivo' => 'Campo inundado por tormenta',
        ]);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Bloqueada Actualmente');
        $respuesta->assertSee('Campo inundado por tormenta');
        $respuesta->assertSee('En Curso');
    }

    // 4. Indicador cuando el estado general de la cancha es Inactiva
    public function test_vista_muestra_alerta_si_cancha_esta_inactiva(): void
    {
        $this->cancha->update(['estado' => 'Inactiva']);

        $respuesta = $this->actingAs($this->admin)
            ->get(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Cancha Inactiva');
        $respuesta->assertSee('Cancha Fuera de Servicio');
    }

    // 5. Usuario con permiso campos.bloquear ve botones y modales de accion
    public function test_usuario_con_permiso_campos_bloquear_ve_botones_y_modales(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->get(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Registrar Bloqueo');
        $respuesta->assertSee('modal-nuevo-bloqueo');
        $respuesta->assertSee('modal-liberar-bloqueo');
    }

    // 6. Usuario con solo permiso de lectura no ve botones ni modales de accion
    public function test_usuario_solo_lectura_no_ve_botones_de_alta_ni_cancelacion(): void
    {
        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::now()->addDay()->setTime(10, 0),
            'fecha_fin' => Carbon::now()->addDay()->setTime(14, 0),
            'motivo' => 'Mantenimiento de pasto sintetico',
        ]);

        $respuesta = $this->actingAs($this->usuarioLectura)
            ->get(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Cancha 5 Estadio Venustiano Carranza');
        $respuesta->assertSee('Mantenimiento de pasto sintetico');
        // No debe ver el boton de nuevo bloqueo ni los modales interactivos de creacion o cancelacion
        $respuesta->assertDontSee('Registrar Bloqueo');
        $respuesta->assertDontSee('id="modal-nuevo-bloqueo"', false);
        $respuesta->assertDontSee('id="modal-liberar-bloqueo"', false);
        $respuesta->assertSee('Solo lectura');
    }

    // 7. Acceso de usuario sin autenticacion es redirigido al login
    public function test_usuario_sin_autenticacion_es_redirigido_al_login(): void
    {
        $respuesta = $this->get(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertRedirect(route('login'));
    }

    // 8. Usuario sin permisos de canchas recibe HTTP 403 Forbidden
    public function test_usuario_sin_permiso_recibe_403_al_intentar_ver_la_vista(): void
    {
        $respuesta = $this->actingAs($this->usuarioSinAcceso)
            ->get(route('canchas.bloqueos.index', $this->cancha));

        $respuesta->assertStatus(403);
    }

    // 9. Envio del formulario web de nuevo bloqueo: crea registro y auditoria, y redirige
    public function test_formulario_web_nuevo_bloqueo_persiste_registro_y_redirige(): void
    {
        $inicio = Carbon::now()->addDays(3)->setTime(8, 0)->format('Y-m-d\TH:i');
        $fin = Carbon::now()->addDays(3)->setTime(12, 0)->format('Y-m-d\TH:i');
        $motivo = 'Aplicacion de fertilizante y riego profundo';

        $respuesta = $this->actingAs($this->admin)
            ->post(route('canchas.bloqueos.store', $this->cancha), [
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'motivo' => $motivo,
            ]);

        $respuesta->assertRedirect(route('canchas.bloqueos.index', $this->cancha));
        $respuesta->assertSessionHas('success', 'Bloqueo de cancha registrado correctamente.');

        $this->assertDatabaseHas('bloqueos_cancha', [
            'cancha_id' => $this->cancha->id,
            'motivo' => $motivo,
        ]);

        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $this->admin->id,
            'accion' => 'crear',
            'tabla_afectada' => 'bloqueos_cancha',
            'motivo' => $motivo,
        ]);
    }

    // 10. Envio del formulario web con traslape: regresa con errores de validacion
    public function test_formulario_web_con_traslape_regresa_con_errores_en_sesion(): void
    {
        $inicioFijo = Carbon::now()->addDays(4)->setTime(10, 0);
        $finFijo = Carbon::now()->addDays(4)->setTime(14, 0);

        BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => $inicioFijo,
            'fecha_fin' => $finFijo,
            'motivo' => 'Bloqueo previo existente',
        ]);

        $respuesta = $this->from(route('canchas.bloqueos.index', $this->cancha))
            ->actingAs($this->admin)
            ->post(route('canchas.bloqueos.store', $this->cancha), [
                'fecha_inicio' => $inicioFijo->format('Y-m-d\TH:i'),
                'fecha_fin' => $finFijo->format('Y-m-d\TH:i'),
                'motivo' => 'Intento de sobreponer bloqueo',
            ]);

        $respuesta->assertRedirect(route('canchas.bloqueos.index', $this->cancha));
        $respuesta->assertSessionHasErrors(['fecha_inicio']);
    }

    // 11. Liberacion de bloqueo desde formulario web: elimina, audita y redirige con exito
    public function test_formulario_web_liberar_bloqueo_elimina_audita_y_redirige(): void
    {
        $bloqueo = BloqueoCancha::create([
            'cancha_id' => $this->cancha->id,
            'fecha_inicio' => Carbon::now()->addDays(5)->setTime(8, 0),
            'fecha_fin' => Carbon::now()->addDays(5)->setTime(16, 0),
            'motivo' => 'Trabajos de pintura en gradas',
        ]);

        $motivoLiberacion = 'Se concluyeron las labores antes de lo previsto';

        $respuesta = $this->actingAs($this->admin)
            ->delete(route('canchas.bloqueos.destroy', [$this->cancha, $bloqueo]), [
                'motivo' => $motivoLiberacion,
            ]);

        $respuesta->assertRedirect(route('canchas.bloqueos.index', $this->cancha));
        $respuesta->assertSessionHas('success', 'Bloqueo de cancha liberado correctamente.');

        $this->assertDatabaseMissing('bloqueos_cancha', ['id' => $bloqueo->id]);

        $this->assertDatabaseHas('auditoria_logs', [
            'usuario_id' => $this->admin->id,
            'accion' => 'eliminar',
            'tabla_afectada' => 'bloqueos_cancha',
            'registro_id' => $bloqueo->id,
            'motivo' => $motivoLiberacion,
        ]);
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario Test Vistas',
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
