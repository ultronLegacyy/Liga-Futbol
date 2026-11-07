<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    // Sembrado de roles de gobernanza y catálogo de permisos según la matriz RBAC.
    public function run(): void
    {
        // Limpiar la caché de permisos de Spatie.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Definición de roles del sistema.
        $roles = [
            // Administración del sistema.
            'Administrador General',

            // Consejo Directivo y Gobernanza.
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision de la Rama Infantil',

            // Roles compatibles de Sprint 1.
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

        // 2. Catálogo completo de permisos.
        $catalogoPermisos = [
            // Gobernanza general.
            'gobernanza.panel',

            // Gestión de ligas.
            'ligas.ver',
            'ligas.crear',
            'ligas.editar',
            'ligas.eliminar',

            // Temporadas deportivas.
            'temporadas.ver',
            'temporadas.crear',
            'temporadas.editar',
            'temporadas.eliminar',
            'temporadas.cerrar',

            // Convocatorias oficiales.
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',
            'convocatorias.eliminar',

            // Reglas de temporada.
            'reglas_temporada.ver',
            'reglas_temporada.editar',

            // Categorías y grupos.
            'categorias.ver',
            'categorias.crear',
            'categorias.editar',
            'categorias.eliminar',

            // Clubes y sedes.
            'clubes.ver',
            'clubes.crear',
            'clubes.editar',
            'clubes.eliminar',

            // Equipos.
            'equipos.ver',
            'equipos.crear',
            'equipos.editar',
            'equipos.eliminar',

            // Jugadores y padrón.
            'jugadores.ver',
            'jugadores.crear',
            'jugadores.editar',
            'jugadores.eliminar',
            'jugadores.transferir',
            'jugadores.validar_edad',

            // Canchas e instalaciones.
            'campos.ver',
            'campos.crear',
            'campos.editar',
            'campos.eliminar',
            'campos.bloquear',

            // Cuentas de usuario.
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'usuarios.asignar_rol',

            // Administración de roles y permisos.
            'roles.ver',
            'roles.crear',
            'roles.editar',
            'roles.eliminar',
            'roles.asignar_permiso',

            // Auditoría.
            'auditoria.ver',

            // Arbitraje y padrón de colegios.
            'arbitros.ver',
            'arbitros.crear',
            'arbitros.editar',
            'arbitros.asignar',

            // Programación de partidos y calendario.
            'partidos.ver',
            'partidos.programar',
            'partidos.reprogramar',

            // Cédulas y captura de resultados.
            'cedulas.ver',
            'cedulas.capturar',
            'cedulas.firmar',
            'resultados.validar',

            // Régimen disciplinario y tribunal de penas.
            'disciplina.ver',
            'disciplina.expedientes',
            'disciplina.sancionar',

            // Finanzas, tesorería y cobros.
            'finanzas.ver',
            'finanzas.cargos',
            'finanzas.pagos_validar',
            'finanzas.reportes',
        ];

        // Crear los permisos de manera persistente.
        foreach ($catalogoPermisos as $nombrePermiso) {
            Permission::firstOrCreate([
                'name' => $nombrePermiso,
                'guard_name' => 'web',
            ]);
        }

        // 3. Administrador General: todos los permisos del catálogo,
        // excepto cedulas.firmar, según la matriz RBAC.
        $permisosAdmin = array_values(array_filter(
            $catalogoPermisos,
            fn (string $permiso): bool => $permiso !== 'cedulas.firmar'
        ));

        $rolesCreados['Administrador General']->syncPermissions(
            $permisosAdmin
        );

        // 4. Roles de gobernanza.

        // Presidente: supervisión institucional, temporadas y reglas.
        $permisosPresidente = [
            'gobernanza.panel',
            'ligas.ver',
            'temporadas.ver',
            'temporadas.crear',
            'temporadas.editar',
            'temporadas.cerrar',
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',
            'reglas_temporada.ver',
            'reglas_temporada.editar',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'campos.ver',
            'finanzas.reportes',
        ];

        $rolesCreados['Presidente']->syncPermissions(
            $permisosPresidente
        );

        // Secretario: consulta de reglas y supervisión documental.
        // No tiene permiso para editar reglas ni registrar clubes o equipos.
        $permisosSecretario = [
            'gobernanza.panel',
            'ligas.ver',
            'temporadas.ver',
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',
            'reglas_temporada.ver',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'jugadores.ver',
            'jugadores.validar_edad',
            'campos.ver',
        ];

        $rolesCreados['Secretario']->syncPermissions(
            $permisosSecretario
        );

        // Tesorero: funciones exclusivamente financieras.
        $permisosTesorero = [
            'gobernanza.panel',
            'finanzas.ver',
            'finanzas.cargos',
            'finanzas.pagos_validar',
            'finanzas.reportes',
        ];

        $rolesCreados['Tesorero']->syncPermissions(
            $permisosTesorero
        );

        // Comisión de la Rama Infantil.
        $permisosComisionInfantil = [
            'gobernanza.panel',
            'temporadas.ver',
            'convocatorias.ver',
            'reglas_temporada.ver',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'jugadores.ver',
            'jugadores.validar_edad',
            'campos.ver',
        ];

        $rolesCreados['Comision de la Rama Infantil']->syncPermissions(
            $permisosComisionInfantil
        );

        // 5. Roles compatibles de Sprint 1.

        // Comisión Disciplinaria.
        $rolesCreados['Comision Disciplinaria']->syncPermissions([
            'disciplina.ver',
            'disciplina.expedientes',
            'disciplina.sancionar',
        ]);

        // Directivo de Club.
        $rolesCreados['Directivo de Club']->syncPermissions([
            'temporadas.ver',
            'convocatorias.ver',
            'categorias.ver',
            'clubes.ver',
            'campos.ver',
        ]);

        // Árbitro.
        $rolesCreados['Arbitro']->syncPermissions([
            'temporadas.ver',
            'campos.ver',
        ]);

        // Consulta Pública.
        $rolesCreados['Consulta Publica']->syncPermissions([
            'temporadas.ver',
            'convocatorias.ver',
        ]);

        // 6. Asignación de roles a usuarios iniciales si existen.

        $adminUser = User::where(
            'email',
            'admin@ligafutbol.com'
        )->first();

        if ($adminUser && ! $adminUser->hasRole('Administrador General')) {
            $adminUser->assignRole('Administrador General');
        }

        $presidenteUser = User::where(
            'email',
            'presidente@ligafutbol.com'
        )->first();

        if ($presidenteUser && ! $presidenteUser->hasRole('Presidente')) {
            $presidenteUser->assignRole('Presidente');
        }

        $secretarioUser = User::where(
            'email',
            'secretario@ligafutbol.com'
        )->first();

        if ($secretarioUser && ! $secretarioUser->hasRole('Secretario')) {
            $secretarioUser->assignRole('Secretario');
        }

        $tesoreroUser = User::where(
            'email',
            'tesoreria@ligafutbol.com'
        )->first();

        if ($tesoreroUser && ! $tesoreroUser->hasRole('Tesorero')) {
            $tesoreroUser->assignRole('Tesorero');
        }

        $comisionInfantilUser = User::where(
            'email',
            'infantil@ligafutbol.com'
        )->first();

        if (
            $comisionInfantilUser
            && ! $comisionInfantilUser->hasRole('Comision de la Rama Infantil')
        ) {
            $comisionInfantilUser->assignRole(
                'Comision de la Rama Infantil'
            );
        }

        $directivoUser = User::where(
            'email',
            'directivo@moreliafc.com'
        )->first();

        if ($directivoUser && ! $directivoUser->hasRole('Directivo de Club')) {
            $directivoUser->assignRole('Directivo de Club');
        }

        $arbitroUser = User::where(
            'email',
            'arbitro@ligafutbol.com'
        )->first();

        if ($arbitroUser && ! $arbitroUser->hasRole('Arbitro')) {
            $arbitroUser->assignRole('Arbitro');
        }
    }
}
