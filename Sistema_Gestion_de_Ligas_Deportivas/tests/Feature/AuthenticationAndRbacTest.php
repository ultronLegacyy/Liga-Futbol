<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

class AuthenticationAndRbacTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Verificar que las tablas de Spatie Permission y Fortify existan.
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
            $this->assertTrue(
                Schema::hasTable($tabla),
                "Falta la tabla de seguridad/RBAC: {$tabla}"
            );
        }

        $this->assertTrue(Schema::hasColumn('usuarios', 'two_factor_secret'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'two_factor_recovery_codes'));
        $this->assertTrue(Schema::hasColumn('usuarios', 'two_factor_confirmed_at'));
    }

    /**
     * Verificar que existan los roles y estén asignados correctamente.
     */
    public function test_roles_rbac_estan_creados_y_asignados(): void
    {
        $this->seed(DatabaseSeeder::class);

        $rolesEsperados = [
            'Administrador General',
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision de la Rama Infantil',
            'Programador de Partidos',
            'Capturista',
            'Tribunal de Penas',
            'Comisión Arbitral',
            'Árbitro en Campo',
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',
        ];

        foreach ($rolesEsperados as $rol) {
            $this->assertDatabaseHas('roles', [
                'name' => $rol,
                'guard_name' => 'web',
            ]);
        }

        // Solo los 13 de la sección 2.3 de la ERS (HU-080, CA1): sin Público General ni los roles retirados del Sprint 1
        $this->assertSame(13, \Spatie\Permission\Models\Role::count());

        foreach (['Comision Disciplinaria', 'Directivo de Club', 'Arbitro', 'Consulta Publica'] as $retirado) {
            $this->assertDatabaseMissing('roles', ['name' => $retirado]);
        }

        $admin = User::where('email', 'admin@ligafutbol.com')->first();

        $this->assertNotNull(
            $admin,
            'El usuario administrador debe existir.'
        );

        $this->assertTrue(
            $admin->hasRole('Administrador General'),
            'El administrador debe tener asignado el rol mediante Spatie.'
        );

        $directivo = User::where(
            'email',
            'directivo@moreliafc.com'
        )->first();

        if ($directivo !== null) {
            $this->assertTrue(
                $directivo->hasRole('Presidente del Club')
            );
        }

        $arbitro = User::where(
            'email',
            'arbitro@ligafutbol.com'
        )->first();

        if ($arbitro !== null) {
            $this->assertTrue(
                $arbitro->hasRole('Árbitro en Campo')
            );
        }
    }

    /**
     * Verificar que Fortify esté registrado y tenga activa la ruta de login.
     */
    public function test_fortify_rutas_y_configuracion_activas(): void
    {
        $this->assertEquals('web', config('fortify.guard'));
        $this->assertEquals('email', config('fortify.username'));

        $this->assertTrue(
            Route::has('login'),
            'La ruta de login de Fortify debe existir.'
        );
    }

    /**
     * Verificar que User permita asignar y retirar permisos dinámicamente.
     */
    public function test_modelo_user_soporta_rbac_dinamico(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'admin@ligafutbol.com')->first();

        $this->assertNotNull(
            $user,
            'El usuario administrador debe existir para probar RBAC.'
        );

        $permisoPrueba = Permission::firstOrCreate([
            'name' => 'administrar_gobernanza',
            'guard_name' => 'web',
        ]);

        $user->givePermissionTo($permisoPrueba);

        $this->assertTrue(
            $user->hasPermissionTo('administrar_gobernanza')
        );

        $user->revokePermissionTo($permisoPrueba);

        $this->assertFalse(
            $user->hasPermissionTo('administrar_gobernanza')
        );
    }
}
