<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
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
        // Roles con sus permisos, como en el resto de las pruebas; Jugador llega con el catálogo de HU-080
        $this->seed(RoleAndPermissionSeeder::class);
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

    // El Administrador no puede desactivar su propia cuenta (CA6)
    public function test_el_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = User::factory()->create(['estatus' => 'activo']);
        $admin->assignRole('Administrador General');

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.toggleStatus', $admin), ['motivo' => 'Intento de autodesactivación'])
            ->assertForbidden();

        $this->assertSame('activo', $admin->fresh()->estatus);
    }

    // El Administrador no puede cambiar sus propios roles: el sistema no se queda sin Administrador General (CA5)
    public function test_el_administrador_no_puede_cambiar_sus_propios_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador General');

        $this->actingAs($admin)
            ->put(route('admin.usuarios.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => ['Jugador'],
                'motivo' => 'Intento de quitarse el rol',
            ])
            ->assertForbidden();

        $this->assertSame(['Administrador General'], $admin->fresh()->getRoleNames()->all());
    }

    // Sin cambiar sus roles, el Administrador sí corrige sus propios datos
    public function test_el_administrador_edita_sus_datos_sin_cambiar_sus_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador General');

        $this->actingAs($admin)
            ->put(route('admin.usuarios.update', $admin), [
                'name' => 'Nombre Corregido',
                'email' => $admin->email,
                'roles' => ['Administrador General'],
                'motivo' => 'Corrección del nombre',
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame('Nombre Corregido', $admin->fresh()->name);
    }

    // El Administrador asigna y retira roles de otra cuenta (CA5)
    public function test_el_administrador_cambia_los_roles_de_otra_cuenta(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador General');
        $usuario = User::factory()->create();
        $usuario->assignRole('Jugador');

        $this->actingAs($admin)
            ->put(route('admin.usuarios.update', $usuario), [
                'name' => $usuario->name,
                'email' => $usuario->email,
                'roles' => ['Tesorero'],
                'motivo' => 'Cambio de funciones',
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame(['Tesorero'], $usuario->fresh()->getRoleNames()->all());
    }
}
