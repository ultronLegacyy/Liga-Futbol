<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// HU-081: consulta en solo lectura de los permisos de cada rol (RF-12.1, IU-02, RNF-29)
class PermisosRolTest extends TestCase
{
    use DatabaseTransactions;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->administrador = $this->crearUsuario('Administrador General');
    }

    // CA1: el listado muestra los 13 roles con cuenta, en el orden del catálogo, y no aparece «Público General»
    public function test_el_listado_muestra_los_13_roles_sin_publico_general(): void
    {
        Role::firstOrCreate(['name' => 'Público General', 'guard_name' => 'web']);

        $respuesta = $this->actingAs($this->administrador)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSeeInOrder(RoleAndPermissionSeeder::ROLES)
            ->assertDontSee('Público General');

        $this->assertCount(13, RoleAndPermissionSeeder::ROLES);
        $this->assertCount(13, $respuesta->viewData('roles'));
    }

    // CA2: el detalle de cada rol coincide con lo que una cuenta de ese rol puede hacer (IU-02)
    public function test_el_detalle_de_cada_rol_coincide_con_su_acceso_real(): void
    {
        $permisos = Permission::query()->pluck('name');

        foreach (RoleAndPermissionSeeder::ROLES as $nombreRol) {
            $rol = Role::findByName($nombreRol);
            $cuenta = $this->crearUsuario($nombreRol);

            $html = $this->actingAs($this->administrador)
                ->get(route('admin.roles.show', $rol->id))
                ->assertOk()
                ->assertSee($nombreRol)
                ->getContent();

            foreach ($permisos as $permiso) {
                $this->assertSame(
                    $cuenta->can($permiso),
                    str_contains($html, 'data-permiso="'.$permiso.'"'),
                    "El detalle de «{$nombreRol}» no coincide con su acceso real en «{$permiso}»."
                );
            }
        }
    }

    // CA2: la coincidencia también se ve al usar el sistema con la cuenta del rol
    public function test_lo_que_muestra_el_detalle_es_lo_que_pasa_al_iniciar_sesion(): void
    {
        $secretario = Role::findByName('Secretario');
        $tesorero = Role::findByName('Tesorero');

        $this->actingAs($this->administrador)->get(route('admin.roles.show', $secretario->id))
            ->assertSee('data-permiso="personas.ver"', false);
        $this->actingAs($this->crearUsuario('Secretario'))->get(route('personas.index'))->assertOk();

        $this->actingAs($this->administrador)->get(route('admin.roles.show', $tesorero->id))
            ->assertDontSee('data-permiso="personas.ver"', false)
            ->assertSeeInOrder(['Personas', 'Sin acceso']);
        $this->actingAs($this->crearUsuario('Tesorero'))->get(route('personas.index'))->assertForbidden();
    }

    // CA3: el detalle sigue siendo de consulta; desde HU-083 solo enlaza al formulario de edición
    public function test_el_detalle_es_de_solo_lectura(): void
    {
        $rol = Role::findByName('Secretario');

        $this->actingAs($this->administrador)
            ->get(route('admin.roles.show', $rol->id))
            ->assertOk()
            ->assertSee('Solo consulta')
            ->assertDontSee('type="checkbox"', false)
            ->assertDontSee('name="permisos', false)
            ->assertDontSee('action="'.route('admin.roles.permisos.update', $rol->id).'"', false)
            ->assertSee('href="'.route('admin.roles.edit', $rol->id).'"', false);
    }

    // CA3: crear, renombrar o borrar roles por petición directa se rechaza y nada cambia.
    // Desde HU-083, PUT /admin/roles/{rol}/permisos es la edición (se prueba en EditarPermisosRolTest)
    public function test_un_cambio_por_peticion_directa_se_rechaza(): void
    {
        $rol = Role::findByName('Tesorero');
        $antes = $rol->permissions()->pluck('name')->sort()->values()->all();
        $intento = ['permisos' => ['usuarios.ver', 'usuarios.crear'], 'permissions' => ['usuarios.ver'], 'name' => 'Otro nombre'];

        $aviso = 'Los roles del catálogo son fijos';

        foreach (['post', 'put', 'patch', 'delete'] as $metodo) {
            $this->actingAs($this->administrador)->{$metodo}('/admin/roles/'.$rol->id, $intento)->assertForbidden()->assertSee($aviso);
        }
        foreach (['post', 'patch', 'delete'] as $metodo) {
            $this->actingAs($this->administrador)->{$metodo}('/admin/roles/'.$rol->id.'/permisos', $intento)->assertForbidden()->assertSee($aviso);
        }
        $this->actingAs($this->administrador)->post('/admin/roles', ['name' => 'Rol nuevo'])->assertForbidden()->assertSee($aviso);

        $rol->refresh();
        $this->assertSame('Tesorero', $rol->name);
        $this->assertSame($antes, $rol->permissions()->pluck('name')->sort()->values()->all());
        $this->assertNull(Role::query()->where('name', 'Rol nuevo')->first());
    }

    // CA4: cualquier rol que no sea Administrador General recibe 403 en el listado, el detalle y los cambios
    public function test_quien_no_es_administrador_general_recibe_403(): void
    {
        $rol = Role::findByName('Secretario');

        foreach (array_diff(RoleAndPermissionSeeder::ROLES, ['Administrador General']) as $nombreRol) {
            $usuario = $this->crearUsuario($nombreRol);

            $respuestas = [
                $this->actingAs($usuario)->get(route('admin.roles.index')),
                $this->actingAs($usuario)->get(route('admin.roles.show', $rol->id)),
                $this->actingAs($usuario)->put('/admin/roles/'.$rol->id.'/permisos', ['permisos' => ['usuarios.ver']]),
            ];

            // La pantalla 403 se muestra en español, no con el texto en inglés de Spatie
            foreach ($respuestas as $respuesta) {
                $respuesta->assertForbidden()
                    ->assertSee('No posee los permisos requeridos para acceder a este recurso administrativo.')
                    ->assertDontSee('User does not have the right roles');
            }
        }
    }

    // Sin sesión no se consulta; un rol fuera del catálogo no existe para esta pantalla
    public function test_sin_sesion_redirige_y_un_rol_fuera_del_catalogo_no_se_muestra(): void
    {
        $publico = Role::firstOrCreate(['name' => 'Público General', 'guard_name' => 'web']);

        $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
        $this->actingAs($this->administrador)->get(route('admin.roles.show', $publico->id))->assertNotFound();
    }

    // Un id que no es número no coincide con ninguna ruta de roles: 404, no 405 en inglés
    public function test_un_id_no_numerico_responde_404(): void
    {
        $this->actingAs($this->administrador)->get('/admin/roles/abc')->assertNotFound();
        $this->actingAs($this->administrador)->post('/admin/roles/abc')->assertNotFound();
        $this->actingAs($this->administrador)->put('/admin/roles/abc/permisos')->assertNotFound();
        $this->actingAs($this->administrador)->put('/admin/roles/1/permisos/abc')->assertNotFound();
    }

    // El enlace «Roles y permisos» del menú solo lo ve el Administrador General
    public function test_el_enlace_del_menu_solo_lo_ve_el_administrador_general(): void
    {
        $enlace = 'href="'.route('admin.roles.index').'"';

        $this->actingAs($this->administrador)->get(route('personas.index'))
            ->assertOk()
            ->assertSee($enlace, false)
            ->assertSee('Roles y permisos');

        $this->actingAs($this->crearUsuario('Secretario'))->get(route('personas.index'))
            ->assertOk()
            ->assertDontSee($enlace, false)
            ->assertDontSee('Roles y permisos');
    }

    // El cambio en errors/403 no tapa los mensajes propios de las políticas (Response::deny)
    public function test_la_pantalla_403_respeta_el_mensaje_de_una_politica(): void
    {
        $this->actingAs($this->crearUsuario('Tesorero'))->get(route('jugadores.index'))
            ->assertForbidden()
            ->assertSee('No cuenta con autorización para consultar el listado de jugadores.');
    }

    private function crearUsuario(string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
