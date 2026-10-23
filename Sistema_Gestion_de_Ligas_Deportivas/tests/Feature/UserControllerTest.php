<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
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

    // CA4: el correo de otra cuenta y un rol que no existe se rechazan sin guardar nada
    public function test_correo_duplicado_y_rol_inexistente_se_rechazan_sin_guardar(): void
    {
        $admin = $this->crearAdministrador();
        $existente = User::factory()->create(['email' => 'ocupado@liga.com']);
        $otra = User::factory()->create(['email' => 'otra@liga.com']);
        $otra->assignRole('Jugador');

        $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'Duplicada',
            'email' => 'ocupado@liga.com',
            'roles' => ['Rol Inventado'],
            'motivo' => 'Alta de prueba',
        ])->assertSessionHasErrors([
            'email' => 'Este correo ya está registrado en el sistema.',
            'roles.0' => 'El rol seleccionado no es válido.',
        ]);

        $this->actingAs($admin)->put(route('admin.usuarios.update', $otra), [
            'name' => $otra->name,
            'email' => $existente->email,
            'roles' => ['Jugador'],
            'motivo' => 'Cambio de correo',
        ])->assertSessionHasErrors(['email' => 'Este correo ya está registrado en otra cuenta.']);

        $this->assertDatabaseMissing('usuarios', ['name' => 'Duplicada']);
        $this->assertSame('otra@liga.com', $otra->fresh()->email);
    }

    // CA6: la cuenta reactivada vuelve a iniciar sesión
    public function test_la_cuenta_reactivada_vuelve_a_iniciar_sesion(): void
    {
        $admin = $this->crearAdministrador();
        $usuario = User::factory()->create(['password' => Hash::make('password123'), 'estatus' => 'inactivo']);

        $this->actingAs($admin)
            ->patch(route('admin.usuarios.toggleStatus', $usuario), ['motivo' => 'Fin de la licencia'])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertSame('activo', $usuario->fresh()->estatus);

        auth()->logout();

        $this->post('/login', ['email' => $usuario->email, 'password' => 'password123']);
        $this->assertAuthenticatedAs($usuario->fresh());
    }

    // CA5: tras cambiar los roles, la cuenta ve solo lo de sus roles vigentes y recibe 403 en lo demás
    public function test_tras_cambiar_roles_la_cuenta_ve_solo_lo_de_sus_roles_vigentes(): void
    {
        $admin = $this->crearAdministrador();
        $usuario = User::factory()->create();
        $usuario->assignRole('Tesorero');

        // El Tesorero no consulta temporadas; el Secretario sí
        $this->actingAs($usuario)->get(route('temporadas.index'))->assertForbidden();

        $this->actingAs($admin)->put(route('admin.usuarios.update', $usuario), [
            'name' => $usuario->name,
            'email' => $usuario->email,
            'roles' => ['Secretario'],
            'motivo' => 'Cambio a la secretaría',
        ])->assertRedirect(route('admin.usuarios.index'));

        $this->actingAs($usuario->fresh())->get(route('temporadas.index'))->assertOk();
        $this->assertFalse($usuario->fresh()->hasRole('Tesorero'));
    }

    // CA7: editar, cambiar roles y desactivar quedan en la auditoría con el usuario, los valores y el motivo
    public function test_edicion_roles_y_estado_quedan_en_la_auditoria_con_motivo(): void
    {
        $admin = $this->crearAdministrador();
        $usuario = User::factory()->create(['name' => 'Nombre Anterior', 'estatus' => 'activo']);
        $usuario->assignRole('Tesorero');

        $this->actingAs($admin)->put(route('admin.usuarios.update', $usuario), [
            'name' => 'Nombre Nuevo',
            'email' => $usuario->email,
            'roles' => ['Secretario'],
            'motivo' => 'Cambio de funciones',
        ]);

        $edicion = $this->logsDe($usuario, 'actualizar')->sole();
        $this->assertSame(['name' => 'Nombre Anterior'], $edicion->valores_anteriores);
        $this->assertSame(['name' => 'Nombre Nuevo'], $edicion->valores_nuevos);
        $this->assertSame('Cambio de funciones', $edicion->motivo);
        $this->assertSame($admin->id, $edicion->usuario_id);

        $retirado = $this->logsDe($usuario, 'rol_retirado')->sole();
        $this->assertContains('Tesorero', $retirado->valores_anteriores['roles']);
        $this->assertSame('Cambio de funciones', $retirado->motivo);

        $asignado = $this->logsDe($usuario, 'rol_asignado')->latest('id')->first();
        $this->assertContains('Secretario', $asignado->valores_nuevos['roles']);
        $this->assertSame('Cambio de funciones', $asignado->motivo);

        $this->actingAs($admin)->patch(route('admin.usuarios.toggleStatus', $usuario), ['motivo' => 'Baja por renuncia']);

        $estado = $this->logsDe($usuario, 'actualizar')->latest('id')->first();
        $this->assertSame(['estatus' => 'activo'], $estado->valores_anteriores);
        $this->assertSame(['estatus' => 'inactivo'], $estado->valores_nuevos);
        $this->assertSame('Baja por renuncia', $estado->motivo);
        $this->assertSame($admin->id, $estado->usuario_id);
    }

    // CA8: quien no es Administrador General recibe 403 al editar o desactivar, y no cambia nada
    public function test_quien_no_es_administrador_recibe_403_al_editar_o_desactivar(): void
    {
        $tesorero = User::factory()->create();
        $tesorero->assignRole('Tesorero');
        $usuario = User::factory()->create(['name' => 'Sin Cambios', 'estatus' => 'activo']);

        $this->actingAs($tesorero)->put(route('admin.usuarios.update', $usuario), [
            'name' => 'Cambiado',
            'email' => $usuario->email,
            'roles' => ['Administrador General'],
            'motivo' => 'Intento sin permiso',
        ])->assertForbidden();

        $this->actingAs($tesorero)
            ->patch(route('admin.usuarios.toggleStatus', $usuario), ['motivo' => 'Intento sin permiso'])
            ->assertForbidden();

        $usuario->refresh();
        $this->assertSame('Sin Cambios', $usuario->name);
        $this->assertSame('activo', $usuario->estatus);
        $this->assertFalse($usuario->hasRole('Administrador General'));
    }

    private function crearAdministrador(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Administrador General');

        return $admin;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AuditoriaLog>
     */
    private function logsDe(User $usuario, string $accion): \Illuminate\Database\Eloquent\Builder
    {
        return AuditoriaLog::query()
            ->where('tabla_afectada', $usuario->getTable())
            ->where('registro_id', $usuario->getKey())
            ->where('accion', $accion);
    }
}
