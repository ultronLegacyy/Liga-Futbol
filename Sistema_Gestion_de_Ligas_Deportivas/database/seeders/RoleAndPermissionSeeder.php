<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    // Sembrado y configuracion de roles oficiales y permisos de gobernanza RBAC
    public function run(): void
    {
        // Limpieza de memoria cache de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Definicion de los 8 roles oficiales del sistema deportivo
        $roles = [
            'Administrador General',
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision Disciplinaria',
            'Directivo de Club',
            'Arbitro',
            'Consulta Publica',
        ];

        $rolesCreados = [];
        foreach ($roles as $nombreRol) {
            $rolesCreados[$nombreRol] = Role::firstOrCreate([
                'name' => $nombreRol,
                'guard_name' => 'web',
            ]);
        }

        // 2. Definicion de permisos atomicos del modulo de gobernanza y administracion
        $permisosGobernanza = [
            // Acceso general al panel de control
            'gobernanza.panel',

            // Gestion de ligas deportivas
            'ligas.ver',
            'ligas.crear',
            'ligas.editar',
            'ligas.eliminar',

            // Gestion de temporadas deportivas
            'temporadas.ver',
            'temporadas.crear',
            'temporadas.editar',
            'temporadas.eliminar',

            // Gestion de convocatorias oficiales
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',
            'convocatorias.eliminar',

            // Gestion de categorias y ramas
            'categorias.ver',
            'categorias.crear',
            'categorias.editar',
            'categorias.eliminar',

            // Gestion de clubes deportivos
            'clubes.ver',
            'clubes.crear',
            'clubes.editar',
            'clubes.eliminar',

            // Gestion de campos e instalaciones
            'campos.ver',
            'campos.crear',
            'campos.editar',
            'campos.eliminar',

            // Gestion de cuentas de usuario y credenciales
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'usuarios.asignar_rol',

            // Administracion de roles y asignacion de permisos
            'roles.ver',
            'roles.crear',
            'roles.editar',
            'roles.eliminar',
            'roles.asignar_permiso',

            // Consulta de registros y bitacora de auditoria
            'auditoria.ver',
        ];

        // Creacion de cada permiso en la base de datos
        foreach ($permisosGobernanza as $nombrePermiso) {
            Permission::firstOrCreate([
                'name' => $nombrePermiso,
                'guard_name' => 'web',
            ]);
        }

        // 3. Asignacion consolidada de los permisos de gobernanza al rol Administrador General
        $rolAdmin = $rolesCreados['Administrador General'];
        $rolAdmin->syncPermissions($permisosGobernanza);

        // 4. Asignacion de permisos base a otros roles del sistema
        // Rol Presidente: Supervision de gobernanza, temporadas, clubes y auditoria
        $rolesCreados['Presidente']->syncPermissions([
            'gobernanza.panel',
            'ligas.ver',
            'temporadas.ver',
            'temporadas.crear',
            'temporadas.editar',
            'convocatorias.ver',
            'categorias.ver',
            'clubes.ver',
            'campos.ver',
            'usuarios.ver',
            'auditoria.ver',
        ]);

        // Rol Secretario: Supervision y lectura de gobernanza, convocatorias y registros
        $rolesCreados['Secretario']->syncPermissions([
            'gobernanza.panel',
            'ligas.ver',
            'temporadas.ver',
            'convocatorias.ver',
            'categorias.ver',
            'clubes.ver',
            'campos.ver',
            'usuarios.ver',
        ]);

        // Rol Directivo de Club: Lectura de estructura general de competencia y convocatorias
        $rolesCreados['Directivo de Club']->syncPermissions([
            'temporadas.ver',
            'convocatorias.ver',
            'categorias.ver',
            'clubes.ver',
            'campos.ver',
        ]);

        // Rol Arbitro: Consulta de programacion deportiva y campos
        $rolesCreados['Arbitro']->syncPermissions([
            'temporadas.ver',
            'campos.ver',
        ]);

        // Rol Consulta Publica: Unicamente visualizacion de temporadas y convocatorias
        $rolesCreados['Consulta Publica']->syncPermissions([
            'temporadas.ver',
            'convocatorias.ver',
        ]);

        // 5. Asignacion automatica de roles a los usuarios iniciales del sistema si existen
        $adminUser = User::where('email', 'admin@ligafutbol.com')->first();
        if ($adminUser && ! $adminUser->hasRole('Administrador General')) {
            $adminUser->assignRole('Administrador General');
        }

        $presidenteUser = User::where('email', 'presidente@ligafutbol.com')->first();
        if ($presidenteUser && ! $presidenteUser->hasRole('Presidente')) {
            $presidenteUser->assignRole('Presidente');
        }

        $tesoreroUser = User::where('email', 'tesoreria@ligafutbol.com')->first();
        if ($tesoreroUser && ! $tesoreroUser->hasRole('Tesorero')) {
            $tesoreroUser->assignRole('Tesorero');
        }

        $directivoUser = User::where('email', 'directivo@moreliafc.com')->first();
        if ($directivoUser && ! $directivoUser->hasRole('Directivo de Club')) {
            $directivoUser->assignRole('Directivo de Club');
        }

        $arbitroUser = User::where('email', 'arbitro@ligafutbol.com')->first();
        if ($arbitroUser && ! $arbitroUser->hasRole('Arbitro')) {
            $arbitroUser->assignRole('Arbitro');
        }
    }
}
