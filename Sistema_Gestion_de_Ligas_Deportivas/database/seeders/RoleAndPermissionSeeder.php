<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    // Sembrado y configuracion de roles de gobernanza y catalogo de permisos segun ERS Seccion 2.3 y Acuerdo PA-17 (HU-071)
    public function run(): void
    {
        // Limpieza de memoria cache de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Roles del sistema: Roles del Consejo Directivo / Gobernanza (HU-071) y roles existentes de Sprint 1
        $roles = [
            // Categoria A: Administracion del Sistema
            'Administrador General',

            // Categoria B: Consejo Directivo y Gobernanza (HU-071)
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision de la Rama Infantil',

            // Roles compatibles de Sprint 1 para no romper suites existentes
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

        // 2. Catalogo completo de permisos atomicos estructurados bajo la convencion modulo.accion
        $catalogoPermisos = [
            // Modulo Gobernanza General
            'gobernanza.panel',

            // Modulo Gestion de Ligas
            'ligas.ver',
            'ligas.crear',
            'ligas.editar',
            'ligas.eliminar',

            // Modulo Temporadas Deportivas
            'temporadas.ver',
            'temporadas.crear',
            'temporadas.editar',
            'temporadas.eliminar',
            'temporadas.cerrar',

            // Modulo Convocatorias Oficiales
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',
            'convocatorias.eliminar',

            // Modulo Reglas de Temporada
            'reglas.ver',
            'reglas.configurar',

            // Modulo Categorias y Grupos
            'categorias.ver',
            'categorias.crear',
            'categorias.editar',
            'categorias.eliminar',

            // Modulo Clubes y Sedes
            'clubes.ver',
            'clubes.crear',
            'clubes.editar',
            'clubes.eliminar',

            // Modulo Equipos
            'equipos.ver',
            'equipos.crear',
            'equipos.editar',
            'equipos.eliminar',

            // Modulo Jugadores y Padron
            'jugadores.ver',
            'jugadores.crear',
            'jugadores.editar',
            'jugadores.eliminar',
            'jugadores.transferir',
            'jugadores.validar_edad',

            // Modulo Canchas e Instalaciones
            'campos.ver',
            'campos.crear',
            'campos.editar',
            'campos.eliminar',
            'campos.bloquear',

            // Modulo Cuentas de Usuario (Exclusivo Administrador General - HU-080 / HU-071 CA4)
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'usuarios.asignar_rol',

            // Modulo Administracion de Roles y Permisos
            'roles.ver',
            'roles.crear',
            'roles.editar',
            'roles.eliminar',
            'roles.asignar_permiso',

            // Modulo Bitacora de Auditoria Inmutable
            'auditoria.ver',

            // Modulo Arbitraje y Padron de Colegios
            'arbitros.ver',
            'arbitros.crear',
            'arbitros.editar',
            'arbitros.asignar',

            // Modulo Programacion de Partidos y Calendario
            'partidos.ver',
            'partidos.programar',
            'partidos.reprogramar',

            // Modulo Cedulas y Captura de Resultados
            'cedulas.ver',
            'cedulas.capturar',
            'cedulas.firmar',
            'resultados.validar',

            // Modulo Regimen Disciplinario y Tribunal de Penas
            'disciplina.ver',
            'disciplina.expedientes',
            'disciplina.sancionar',

            // Modulo Finanzas, Tesoreria y Cobros
            'finanzas.ver',
            'finanzas.cargos',
            'finanzas.pagos_validar',
            'finanzas.reportes',
        ];

        // Creacion persistente de cada permiso en la base de datos
        foreach ($catalogoPermisos as $nombrePermiso) {
            Permission::firstOrCreate([
                'name' => $nombrePermiso,
                'guard_name' => 'web',
            ]);
        }

        // 3. Asignacion consolidada de permisos al Administrador General (salvo cedulas.firmar segun matriz)
        $permisosAdmin = array_values(array_filter($catalogoPermisos, fn (string $permiso): bool => $permiso !== 'cedulas.firmar'));
        $rolesCreados['Administrador General']->syncPermissions($permisosAdmin);

        // 4. Asignacion granular para los 4 roles de Gobernanza (S2-T10 / HU-071)
        // Rol Presidente: maxima autoridad institucional, supervision de torneos y finanzas ejecutivas (sin gestion de cuentas ni canchas operativas)
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
            'reglas.ver',
            'reglas.configurar',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'campos.ver',
            'finanzas.reportes',
        ];
        $rolesCreados['Presidente']->syncPermissions($permisosPresidente);

        // Rol Secretario: fe publica, convocatorias, inscripcion de clubes y equipos, padron y validacion etaria
        $permisosSecretario = [
            'gobernanza.panel',
            'ligas.ver',
            'temporadas.ver',
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',
            'reglas.ver',
            'categorias.ver',
            'clubes.ver',
            'clubes.crear',
            'clubes.editar',
            'equipos.ver',
            'equipos.crear',
            'equipos.editar',
            'jugadores.ver',
            'jugadores.validar_edad',
            'campos.ver',
        ];
        $rolesCreados['Secretario']->syncPermissions($permisosSecretario);

        // Rol Tesorero: administracion financiera, cargos, pagos y reportes (aislamiento total de disciplina y competicion)
        $permisosTesorero = [
            'gobernanza.panel',
            'finanzas.ver',
            'finanzas.cargos',
            'finanzas.pagos_validar',
            'finanzas.reportes',
            'clubes.ver',
            'equipos.ver',
        ];
        $rolesCreados['Tesorero']->syncPermissions($permisosTesorero);

        // Rol Comision de la Rama Infantil: categorias menores, control estricto de edad y acreditacion infantil (PA-17)
        $permisosComisionInfantil = [
            'gobernanza.panel',
            'temporadas.ver',
            'convocatorias.ver',
            'reglas.ver',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'jugadores.ver',
            'jugadores.validar_edad',
            'campos.ver',
        ];
        $rolesCreados['Comision de la Rama Infantil']->syncPermissions($permisosComisionInfantil);

        // 5. Roles compatibles de Sprint 1: conservan permisos minimos para no romper suites existentes
        $rolesCreados['Comision Disciplinaria']->syncPermissions([
            'disciplina.ver',
            'disciplina.expedientes',
            'disciplina.sancionar',
        ]);

        $rolesCreados['Directivo de Club']->syncPermissions([
            'temporadas.ver',
            'convocatorias.ver',
            'categorias.ver',
            'clubes.ver',
            'campos.ver',
        ]);

        $rolesCreados['Arbitro']->syncPermissions([
            'temporadas.ver',
            'campos.ver',
        ]);

        $rolesCreados['Consulta Publica']->syncPermissions([
            'temporadas.ver',
            'convocatorias.ver',
        ]);

        // 6. Asignacion automatica de roles a los usuarios iniciales del sistema si existen
        $adminUser = User::where('email', 'admin@ligafutbol.com')->first();
        if ($adminUser && ! $adminUser->hasRole('Administrador General')) {
            $adminUser->assignRole('Administrador General');
        }

        $presidenteUser = User::where('email', 'presidente@ligafutbol.com')->first();
        if ($presidenteUser && ! $presidenteUser->hasRole('Presidente')) {
            $presidenteUser->assignRole('Presidente');
        }

        $secretarioUser = User::where('email', 'secretario@ligafutbol.com')->first();
        if ($secretarioUser && ! $secretarioUser->hasRole('Secretario')) {
            $secretarioUser->assignRole('Secretario');
        }

        $tesoreroUser = User::where('email', 'tesoreria@ligafutbol.com')->first();
        if ($tesoreroUser && ! $tesoreroUser->hasRole('Tesorero')) {
            $tesoreroUser->assignRole('Tesorero');
        }

        $comisionInfantilUser = User::where('email', 'infantil@ligafutbol.com')->first();
        if ($comisionInfantilUser && ! $comisionInfantilUser->hasRole('Comision de la Rama Infantil')) {
            $comisionInfantilUser->assignRole('Comision de la Rama Infantil');
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
