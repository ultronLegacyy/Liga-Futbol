<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

class AuthenticationAndRbacTest extends TestCase
{
    /**
     * Verificar que las tablas de Spatie Permission y Fortify existan en la base de datos.
     */
    public function test_tablas_de_fortify_y_spatie_existen(): void
    {
        $tablasSeguridad = [
            'roles',
            'permissions',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',
            'passkeys',
        ];

        foreach ($tablasSeguridad as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla de seguridad/RBAC: {$tabla}");
        }

        // Verificar columnas 2FA en usuarios
        $this->assertTrue(Schema::hasColumn('usuarios', 'two_factor_secret'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'two_factor_recovery_codes'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'two_factor_confirmed_at'));
    }

    /**
     * Verificar que los roles base de RBAC existan y esten asignados a los usuarios correspondientes.
     */
    public function test_roles_rbac_estan_creados_y_asignados(): void
    {
        $rolesEsperados = [
            'Administrador General',
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision Disciplinaria',
            'Directivo de Club',
            'Arbitro',
            'Consulta Publica',
        ];

        foreach ($rolesEsperados as $rol) {
            $this->assertDatabaseHas('roles', [
                'name' => $rol,
                'guard_name' => 'web',
            ]);
        }

        // Verificar asignacion de rol con Spatie en el usuario administrador
        $admin = User::where('email', 'admin@ligafutbol.com')->first();
        $this->assertNotNull($admin, 'El usuario administrador debe existir');
        $this->assertTrue($admin->hasRole('Administrador General'), 'El usuario admin debe tener el rol Administrador General asignado via Spatie');

        // Verificar asignacion de rol en directivo
        $directivo = User::where('email', 'directivo@ligafutbol.com')->first();
        if ($directivo) {
            $this->assertTrue($directivo->hasRole('Directivo de Club'));
        }

        // Verificar asignacion de rol en arbitro
        $arbitro = User::where('email', 'arbitro@ligafutbol.com')->first();
        if ($arbitro) {
            $this->assertTrue($arbitro->hasRole('Arbitro'));
        }
    }

    /**
     * Verificar que Fortify este registrado y exponga las rutas base de autenticacion.
     */
    public function test_fortify_rutas_y_configuracion_activas(): void
    {
        $this->assertEquals('web', config('fortify.guard'));
        $this->assertEquals('email', config('fortify.username'));

        // Verificar registro de rutas base de Fortify
        $this->assertTrue(Route::has('login'), 'La ruta de login de Fortify debe existir');
    }

    /**
     * Verificar que el modelo User implemente HasRoles para verificacion dinamica de permisos.
     */
    public function test_modelo_user_soporta_rbac_dinamico(): void
    {
        $user = User::where('email', 'admin@ligafutbol.com')->first();
        $this->assertNotNull($user);

        // Crear permiso de prueba y asignarlo
        $permisoPrueba = Permission::firstOrCreate(['name' => 'administrar_gobernanza', 'guard_name' => 'web']);
        $user->givePermissionTo($permisoPrueba);

        $this->assertTrue($user->hasPermissionTo('administrar_gobernanza'));

        // Limpiar permiso de prueba
        $user->revokePermissionTo($permisoPrueba);
        $this->assertFalse($user->hasPermissionTo('administrar_gobernanza'));
    }
}
