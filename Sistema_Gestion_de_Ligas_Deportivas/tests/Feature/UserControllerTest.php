<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Crear roles necesarios para las pruebas
        Role::firstOrCreate(['name' => 'Administrador General', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Jugador', 'guard_name' => 'web']);
    }

    public function test_quien_no_es_administrador_recibe_403_en_acciones()
    {
        $usuarioComun = User::factory()->create();
        $usuarioComun->assignRole('Jugador');

        $this->actingAs($usuarioComun);

        // Listado
        $response = $this->get(route('admin.usuarios.index'));
        $response->assertStatus(403);

        // Alta
        $response = $this->post(route('admin.usuarios.store'), []);
        $response->assertStatus(403);
    }

    public function test_administrador_puede_crear_cuenta_con_contrasena_temporal()
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador General');

        $this->actingAs($admin);

        $response = $this->post(route('admin.usuarios.store'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@liga.com',
            'roles' => ['Jugador'],
            'motivo' => 'Alta de prueba',
        ]);

        $response->assertRedirect(route('admin.usuarios.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('usuarios', [
            'email' => 'nuevo@liga.com',
            'estatus' => 'activo',
            'requires_password_change' => 1,
        ]);
    }

    public function test_validaciones_al_crear_usuario_devuelven_mensajes_en_espanol()
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador General');

        $this->actingAs($admin);

        $response = $this->post(route('admin.usuarios.store'), [
            // Faltan datos intencionalmente
        ]);

        $response->assertSessionHasErrors([
            'name' => 'El nombre es obligatorio.',
            'email' => 'El correo electrónico es obligatorio.',
            'roles' => 'Debes asignar al menos un rol al usuario.',
            'motivo' => 'El motivo es obligatorio para el registro de auditoría.',
        ]);
    }

    public function test_desactivar_y_reactivar_cuenta_cambia_estatus_y_requiere_motivo()
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador General');

        $usuario = User::factory()->create(['estatus' => 'activo']);

        $this->actingAs($admin);

        // Intentar sin motivo (falla)
        $response = $this->patch(route('admin.usuarios.toggleStatus', $usuario), []);
        $response->assertSessionHasErrors('motivo');

        // Intentar con motivo (éxito)
        $response = $this->patch(route('admin.usuarios.toggleStatus', $usuario), [
            'motivo' => 'Baja temporal por sanción'
        ]);

        $response->assertRedirect(route('admin.usuarios.index'));
        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'estatus' => 'inactivo'
        ]);
    }

    public function test_cuenta_desactivada_no_inicia_sesion()
    {
        $usuario = User::factory()->create([
            'password' => Hash::make('password123'), // <--- CAMBIO AQUÍ: Se reemplazó bcrypt() por Hash::make()
            'estatus' => 'inactivo'
        ]);

        $response = $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password123',
        ]);

        // Fortify debe rechazar el acceso de cuentas inactivas
        $this->assertGuest();
    }
}