<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Pantallas de la administración de cuentas y del cambio obligatorio de contraseña (HU-080, S2-T14)
class UsuariosVistasTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = $this->crearUsuario('admin_vistas_usuarios@liga.com', 'Administrador General');
    }

    // El listado muestra las demás cuentas con sus roles, su estado y sus acciones
    public function test_el_listado_muestra_las_cuentas_con_sus_roles_y_acciones(): void
    {
        $tesorero = $this->crearUsuario('tesorero_vistas_usuarios@liga.com', 'Tesorero');

        $this->actingAs($this->admin)->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertViewIs('admin.usuarios.index')
            ->assertSee('tesorero_vistas_usuarios@liga.com')
            ->assertSee('Tesorero')
            ->assertSee('Activo')
            ->assertSee(route('admin.usuarios.create'), false)
            ->assertSee(route('admin.usuarios.edit', $tesorero), false)
            ->assertSee(route('admin.usuarios.estado', $tesorero), false)
            ->assertDontSee(route('admin.usuarios.edit', $this->admin), false);
    }

    // CA2: el alta muestra la contraseña temporal real una sola vez
    public function test_el_alta_muestra_la_contrasena_temporal_una_sola_vez(): void
    {
        $this->actingAs($this->admin)->get(route('admin.usuarios.create'))
            ->assertOk()
            ->assertSee('Registrar cuenta')
            ->assertSee('name="roles[]" value="Secretario"', false);

        $this->actingAs($this->admin)
            ->from(route('admin.usuarios.create'))
            ->post(route('admin.usuarios.store'), [
                'name' => 'Secretaria del Consejo',
                'email' => 'secretaria_vistas@liga.com',
                'roles' => ['Secretario'],
                'motivo' => 'Alta de la nueva secretaria',
            ])
            ->assertRedirect(route('admin.usuarios.index'));

        $temporal = session('password_temporal')['password'];
        $usuario = User::where('email', 'secretaria_vistas@liga.com')->sole();

        $this->assertTrue(Hash::check($temporal, $usuario->password));
        $this->assertGreaterThanOrEqual(15, mb_strlen($temporal));

        $this->actingAs($this->admin)->get(route('admin.usuarios.index'))
            ->assertSee('Cuenta creada correctamente.')
            ->assertSee('Contraseña temporal de secretaria_vistas@liga.com')
            ->assertSee($temporal)
            ->assertSee('Debe cambiar su contraseña');

        $this->actingAs($this->admin)->get(route('admin.usuarios.index'))
            ->assertDontSee($temporal);
    }

    // CA4: un dato inválido vuelve al formulario con el mensaje en español y conserva lo capturado
    public function test_un_dato_invalido_vuelve_al_formulario_con_mensajes_en_espanol(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.usuarios.create'))
            ->followingRedirects()
            ->post(route('admin.usuarios.store'), [
                'name' => 'Persona sin roles',
                'email' => 'no-es-un-correo',
                'motivo' => '',
            ])
            ->assertOk()
            ->assertViewIs('admin.usuarios.create')
            ->assertSee('No se guardó la cuenta. Revisa los campos marcados.')
            ->assertSee('El formato del correo es inválido.')
            ->assertSee('Debes asignar al menos un rol al usuario.')
            ->assertSee('El motivo es obligatorio para el registro de auditoría.')
            ->assertSee('value="Persona sin roles"', false)
            ->assertSee('aria-invalid="true"', false);

        $this->assertDatabaseMissing('usuarios', ['name' => 'Persona sin roles']);
    }

    // CA5: la edición parte de los datos y roles actuales
    public function test_la_edicion_parte_de_los_datos_y_roles_actuales(): void
    {
        $tesorero = $this->crearUsuario('tesorero_edicion_vistas@liga.com', 'Tesorero');

        $html = $this->actingAs($this->admin)->get(route('admin.usuarios.edit', $tesorero))
            ->assertOk()
            ->assertSee('Editar cuenta')
            ->assertSee('value="tesorero_edicion_vistas@liga.com"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/value="Tesorero"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="Secretario"[^>]*checked/', $html);
    }

    // CA6: la pantalla de estado desactiva y reactiva con motivo
    public function test_la_pantalla_de_estado_desactiva_y_reactiva_la_cuenta(): void
    {
        $tesorero = $this->crearUsuario('tesorero_estado_vistas@liga.com', 'Tesorero');

        $this->actingAs($this->admin)->get(route('admin.usuarios.estado', $tesorero))
            ->assertOk()
            ->assertSee('Desactivar cuenta');

        $this->actingAs($this->admin)
            ->from(route('admin.usuarios.estado', $tesorero))
            ->patch(route('admin.usuarios.toggleStatus', $tesorero), ['motivo' => 'Licencia temporal'])
            ->assertRedirect(route('admin.usuarios.index'));

        $this->actingAs($this->admin)->get(route('admin.usuarios.index'))
            ->assertSee('Cuenta desactivada.')
            ->assertSee('Inactivo')
            ->assertSee('Reactivar');

        $this->actingAs($this->admin)->get(route('admin.usuarios.estado', $tesorero))
            ->assertOk()
            ->assertSee('Reactivar cuenta');
    }

    // El Administrador no tiene pantalla para desactivar su propia cuenta
    public function test_el_administrador_no_puede_abrir_el_estado_de_su_propia_cuenta(): void
    {
        $this->actingAs($this->admin)->get(route('admin.usuarios.estado', $this->admin))->assertForbidden();
    }

    // CA8: quien no es Administrador General recibe 403 en todas las pantallas
    public function test_quien_no_es_administrador_recibe_403_en_las_pantallas(): void
    {
        $tesorero = $this->crearUsuario('tesorero_403_vistas@liga.com', 'Tesorero');

        foreach ([
            route('admin.usuarios.index'),
            route('admin.usuarios.create'),
            route('admin.usuarios.edit', $this->admin),
            route('admin.usuarios.estado', $this->admin),
        ] as $url) {
            $this->actingAs($tesorero)->get($url)->assertForbidden();
        }
    }

    // CA3: la pantalla de cambio obligatorio abre y muestra los errores en español
    public function test_la_pantalla_de_cambio_obligatorio_abre_y_muestra_errores_en_espanol(): void
    {
        $nuevo = $this->crearUsuario('primer_ingreso_vistas@liga.com', 'Tesorero');
        $nuevo->forceFill(['requires_password_change' => true])->save();

        $this->actingAs($nuevo)->get(route('password.force-change.show'))
            ->assertOk()
            ->assertSee('Cambia tu contraseña')
            ->assertSee('name="password_confirmation"', false);

        $this->actingAs($nuevo)
            ->from(route('password.force-change.show'))
            ->followingRedirects()
            ->post(route('password.force-change.update'), ['password' => '', 'password_confirmation' => ''])
            ->assertOk()
            ->assertSee('La nueva contraseña es obligatoria.');

        $this->actingAs($nuevo)
            ->from(route('password.force-change.show'))
            ->followingRedirects()
            ->post(route('password.force-change.update'), ['password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertOk()
            ->assertSee('La contraseña debe tener al menos 15 caracteres.');

        $this->assertTrue($nuevo->fresh()->requires_password_change);
    }

    // El menú lateral lleva a la administración de cuentas
    public function test_el_menu_enlaza_la_administracion_de_cuentas(): void
    {
        $this->actingAs($this->admin)->get(route('temporadas.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.usuarios.index').'"', false);
    }

    private function crearUsuario(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => $rol,
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
