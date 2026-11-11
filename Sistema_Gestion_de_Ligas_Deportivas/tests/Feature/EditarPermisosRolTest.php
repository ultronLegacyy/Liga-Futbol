<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

// HU-083: edición de los permisos de cada rol (RF-12.1, RNF-33, RNF-29, IU-02)
class EditarPermisosRolTest extends TestCase
{
    use DatabaseTransactions;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->administrador = $this->crearUsuario('Administrador General');
    }

    // CA1: la pantalla de edición parte de los permisos actuales del rol
    public function test_el_formulario_muestra_los_permisos_actuales_del_rol(): void
    {
        $rol = Role::findByName('Tesorero');
        $permiso = $rol->permissions()->value('name');

        $this->actingAs($this->administrador)
            ->get(route('admin.roles.edit', $rol->id))
            ->assertOk()
            ->assertSee('Editar permisos de Tesorero')
            ->assertSee('value="'.$permiso.'" checked', false)
            ->assertDontSee('value="personas.ver" checked', false)
            ->assertSee('name="motivo"', false);
    }

    // CA1: agregar y quitar un permiso se guarda y aplica a las cuentas que tienen ese rol
    public function test_agregar_y_quitar_permisos_se_guarda_y_aplica_a_las_cuentas(): void
    {
        $rol = Role::findByName('Tesorero');
        $cuenta = $this->crearUsuario('Tesorero');
        $quitado = $rol->permissions()->orderBy('name')->value('name');
        $this->actingAs($cuenta)->get(route('personas.index'))->assertForbidden();

        $nuevos = $rol->permissions()->pluck('name')->reject(fn (string $p): bool => $p === $quitado)->push('personas.ver')->values()->all();

        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => $nuevos, 'motivo' => 'Tesorería consulta el padrón para los cobros'])
            ->assertRedirect(route('admin.roles.show', $rol->id))
            ->assertSessionHas('success', 'Se guardaron los permisos de Tesorero: 1 agregado(s) y 1 quitado(s). El cambio ya aplica a sus cuentas.');

        $rol->refresh();
        $this->assertTrue($rol->hasPermissionTo('personas.ver'));
        $this->assertFalse($rol->hasPermissionTo($quitado));
        $this->assertNotNull($rol->getAttribute('permisos_editados_en'));

        // La cuenta con el rol ya puede entrar, sin cambiarle nada a la cuenta
        $cuenta = $cuenta->fresh();
        $this->assertNotNull($cuenta);
        $this->assertTrue($cuenta->can('personas.ver'));
        $this->assertFalse($cuenta->can($quitado));
        $this->actingAs($cuenta)->get(route('personas.index'))->assertOk();

        // La consulta de HU-081 muestra el cambio
        $this->actingAs($this->administrador)
            ->get(route('admin.roles.show', $rol->id))
            ->assertSee('data-permiso="personas.ver"', false)
            ->assertDontSee('data-permiso="'.$quitado.'"', false);
    }

    // CA1: si se desmarcan todas las casillas, el rol se queda sin permisos
    public function test_se_pueden_quitar_todos_los_permisos_de_un_rol(): void
    {
        $rol = Role::findByName('Capturista');

        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $rol->id), ['motivo' => 'Rol suspendido mientras se revisa la captura'])
            ->assertRedirect(route('admin.roles.show', $rol->id));

        $this->assertSame(0, $rol->refresh()->permissions()->count());
    }

    // CA2: el cambio queda en la bitácora con usuario, fecha y hora, IP, valor anterior, valor nuevo y motivo
    public function test_el_cambio_queda_registrado_de_forma_inmutable(): void
    {
        $rol = Role::findByName('Tesorero');
        $antes = $rol->permissions()->orderBy('name')->pluck('name')->all();
        $despues = collect($antes)->push('personas.ver')->sort()->values()->all();

        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => $despues, 'motivo' => 'Acuerdo del Consejo Directivo'])
            ->assertRedirect();

        $registro = AuditoriaLog::query()->where('accion', 'permisos_rol_editados')->where('registro_id', $rol->id)->sole();

        $this->assertSame('roles', $registro->tabla_afectada);
        $this->assertSame($this->administrador->id, $registro->usuario_id);
        $this->assertNotNull($registro->created_at);
        $this->assertSame('127.0.0.1', $registro->ip);
        $this->assertSame('Acuerdo del Consejo Directivo', $registro->motivo);
        $this->assertSame(['permisos' => $antes], $registro->valores_anteriores);
        $this->assertSame(['permisos' => $despues, 'agregados' => ['personas.ver'], 'quitados' => []], $registro->valores_nuevos);

        // Inmutable (HU-004): no se puede cambiar ni borrar
        $this->expectException(\App\Exceptions\AuditoriaInmutableException::class);
        $registro->update(['motivo' => 'otro']);
    }

    // CA2: el motivo es obligatorio; sin motivo no cambia nada ni se registra nada
    public function test_sin_motivo_no_se_guarda_el_cambio(): void
    {
        $rol = Role::findByName('Tesorero');
        $antes = $rol->permissions()->pluck('name')->sort()->values()->all();

        $this->actingAs($this->administrador)
            ->from(route('admin.roles.edit', $rol->id))
            ->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => ['personas.ver'], 'motivo' => '  '])
            ->assertRedirect(route('admin.roles.edit', $rol->id))
            ->assertSessionHasErrors(['motivo' => 'Escribe el motivo del cambio; quedará en la bitácora.']);

        $this->assertSame($antes, $rol->refresh()->permissions()->pluck('name')->sort()->values()->all());
        $this->assertSame(0, AuditoriaLog::query()->where('accion', 'permisos_rol_editados')->count());
        $this->assertNull($rol->getAttribute('permisos_editados_en'));
    }

    // CA2: el cambio y su registro van en una sola transacción: si la bitácora falla, los permisos no cambian
    public function test_si_falla_el_registro_no_cambian_los_permisos(): void
    {
        $rol = Role::findByName('Tesorero');
        $antes = $rol->permissions()->pluck('name')->sort()->values()->all();
        AuditoriaLog::creating(function (): never {
            throw new RuntimeException('Falla simulada de la bitácora');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->administrador)
                ->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => ['personas.ver'], 'motivo' => 'Prueba de transacción']);
            $this->fail('Se esperaba la falla de la bitácora.');
        } catch (RuntimeException $falla) {
            $this->assertSame('Falla simulada de la bitácora', $falla->getMessage());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $rol = Role::query()->findOrFail($rol->id);
        $this->assertSame($antes, $rol->permissions()->pluck('name')->sort()->values()->all());
        $this->assertNull($rol->getAttribute('permisos_editados_en'));
    }

    // Datos inválidos: un permiso fuera del catálogo o un guardado sin cambios se rechazan
    public function test_permisos_inexistentes_o_sin_cambios_se_rechazan(): void
    {
        $rol = Role::findByName('Tesorero');
        $actuales = $rol->permissions()->pluck('name')->all();

        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => ['permiso.inventado'], 'motivo' => 'Prueba'])
            ->assertSessionHasErrors(['permisos.0' => 'El permiso «permiso.inventado» no existe en el catálogo.']);

        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => $actuales, 'motivo' => 'Prueba'])
            ->assertSessionHasErrors(['permisos' => 'No cambiaste ningún permiso: marca o desmarca al menos una acción.']);

        $this->assertSame(0, AuditoriaLog::query()->where('accion', 'permisos_rol_editados')->count());
    }

    // CA3: quien no es Administrador General recibe 403, con la pantalla o por petición directa, y nada cambia
    public function test_quien_no_es_administrador_general_recibe_403(): void
    {
        $rol = Role::findByName('Tesorero');
        $antes = $rol->permissions()->pluck('name')->sort()->values()->all();

        // Sin sesión se pide iniciar sesión
        $this->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => ['usuarios.ver'], 'motivo' => 'Sin sesión'])
            ->assertRedirect(route('login'));

        foreach (array_diff(RoleAndPermissionSeeder::ROLES, ['Administrador General']) as $nombreRol) {
            $usuario = $this->crearUsuario($nombreRol);

            $this->actingAs($usuario)->get(route('admin.roles.edit', $rol->id))
                ->assertForbidden()
                ->assertSee('No posee los permisos requeridos para acceder a este recurso administrativo.');
            $this->actingAs($usuario)->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => ['usuarios.ver'], 'motivo' => 'Intento'])
                ->assertForbidden();
            $this->actingAs($usuario)->putJson(route('admin.roles.permisos.update', $rol->id), ['permisos' => ['usuarios.ver'], 'motivo' => 'Intento'])
                ->assertForbidden();
        }

        $this->assertSame($antes, $rol->refresh()->permissions()->pluck('name')->sort()->values()->all());
        $this->assertSame(0, AuditoriaLog::query()->where('accion', 'permisos_rol_editados')->count());
    }

    // CA4: al volver a ejecutar la carga inicial, el rol editado conserva sus permisos; los demás se siguen sincronizando
    public function test_el_seeder_conserva_los_permisos_editados(): void
    {
        $tesorero = Role::findByName('Tesorero');
        $nuevos = $tesorero->permissions()->pluck('name')->push('personas.ver')->values()->all();
        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $tesorero->id), ['permisos' => $nuevos, 'motivo' => 'Ajuste de Tesorería'])
            ->assertRedirect();

        // Un rol que nadie editó, pero al que le falta un permiso, vuelve a su valor original
        $secretario = Role::findByName('Secretario');
        $secretario->revokePermissionTo('personas.ver');

        $this->seed(RoleAndPermissionSeeder::class);
        // «Reiniciar el sistema»: se borra la caché de permisos y se leen de la base
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue(Role::findByName('Tesorero')->hasPermissionTo('personas.ver'));
        $this->assertEqualsCanonicalizing($nuevos, Role::findByName('Tesorero')->permissions()->pluck('name')->all());
        $this->assertTrue(Role::findByName('Secretario')->hasPermissionTo('personas.ver'));
    }

    // Supuesto de PA-18: el Administrador General no puede quitarse los permisos de administrar cuentas y permisos
    public function test_el_administrador_no_puede_quitarse_los_permisos_de_administrar(): void
    {
        $rol = Role::findByName('Administrador General');
        $antes = $rol->permissions()->pluck('name')->sort()->values()->all();
        $sinUsuarios = collect($antes)->reject(fn (string $p): bool => $p === 'usuarios.editar' || $p === 'roles.asignar_permiso')->values()->all();

        $this->actingAs($this->administrador)
            ->get(route('admin.roles.edit', $rol->id))
            ->assertOk()
            ->assertSee('El Administrador General no puede quitarse los permisos de administrar cuentas y permisos');

        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => $sinUsuarios, 'motivo' => 'Prueba PA-18'])
            ->assertSessionHasErrors(['permisos' => 'El Administrador General no puede quitarse los permisos de administrar cuentas y permisos (roles.asignar_permiso, usuarios.editar).']);

        $this->assertSame($antes, $rol->refresh()->permissions()->pluck('name')->sort()->values()->all());

        // Otros permisos del Administrador General sí se pueden quitar
        $sinLigas = collect($antes)->reject(fn (string $p): bool => $p === 'ligas.eliminar')->values()->all();
        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $rol->id), ['permisos' => $sinLigas, 'motivo' => 'Prueba PA-18 permitida'])
            ->assertRedirect(route('admin.roles.show', $rol->id));
        $this->assertFalse($rol->refresh()->hasPermissionTo('ligas.eliminar'));
    }

    // Un rol fuera del catálogo o un id inexistente no se edita
    public function test_un_rol_fuera_del_catalogo_no_se_edita(): void
    {
        $publico = Role::firstOrCreate(['name' => 'Público General', 'guard_name' => 'web']);

        $this->actingAs($this->administrador)->get(route('admin.roles.edit', $publico->id))->assertNotFound();
        $this->actingAs($this->administrador)
            ->put(route('admin.roles.permisos.update', $publico->id), ['permisos' => ['usuarios.ver'], 'motivo' => 'Prueba'])
            ->assertNotFound();
        $this->assertSame(0, $publico->permissions()->count());
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
