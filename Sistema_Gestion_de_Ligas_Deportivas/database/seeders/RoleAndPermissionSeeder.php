<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    // Catalogo de roles de la seccion 2.3 de la ERS. Los del Sprint 1 que no estan en la ERS pasaron a sus equivalentes (migracion 2026_10_23_120000)
    public const ROLES = [
        // Administracion del sistema
        'Administrador General',

        // Consejo Directivo y gobernanza (HU-071)
        'Presidente',
        'Secretario',
        'Tesorero',
        'Comision de la Rama Infantil',

        // Operacion deportiva y disciplina (HU-072)
        'Programador de Partidos',
        'Capturista',
        'Tribunal de Penas',
        'Comisión Arbitral',
        'Árbitro en Campo',

        // Clubes y jugadores (HU-073)
        'Presidente del Club',
        'Delegado del Club',
        'Jugador',
    ];

    // Sembrado y configuracion de roles de gobernanza y catalogo de permisos segun ERS Seccion 2.3 y Acuerdo PA-17 (HU-071)
    public function run(): void
    {
        // Limpieza de memoria cache de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Los 13 roles con cuenta de la seccion 2.3 de la ERS (HU-080, CA1); el Publico General no tiene cuenta
        $roles = self::ROLES;

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

            // Modulo Reglas de Temporada (Homologado con HU-006 / PR #28)
            'reglas_temporada.ver',
            'reglas_temporada.editar',

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
            'reglas_temporada.ver',
            'reglas_temporada.editar',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'campos.ver',
            'finanzas.reportes',
        ];
        $rolesCreados['Presidente']->syncPermissions($permisosPresidente);

        // Rol Secretario: fe publica, convocatorias, padron y supervision documental etaria (sin registro de clubes ni equipos, facultad de Delegado de Club)
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
        $rolesCreados['Secretario']->syncPermissions($permisosSecretario);

        // Rol Tesorero: administracion financiera, cargos, pagos y reportes (aislamiento total de disciplina y competicion)
        $permisosTesorero = [
            'gobernanza.panel',
            'finanzas.ver',
            'finanzas.cargos',
            'finanzas.pagos_validar',
            'finanzas.reportes',
        ];
        $rolesCreados['Tesorero']->syncPermissions($permisosTesorero);

        // Rol Comision de la Rama Infantil: categorias menores, control estricto de edad y acreditacion infantil (PA-17)
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
        $rolesCreados['Comision de la Rama Infantil']->syncPermissions($permisosComisionInfantil);

        // 5. Asignacion granular para los 5 roles Operativos Deportivos (HU-072 / SCRUM-15 / S2-T16)
        // Rol Programador de Partidos: logistica de campos, bloqueos, programacion de jornadas y validacion de resultados
        $permisosProgramador = [
            'temporadas.ver',
            'convocatorias.ver',
            'reglas_temporada.ver',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'jugadores.ver',
            'campos.ver',
            'campos.crear',
            'campos.editar',
            'campos.bloquear',
            'arbitros.ver',
            'arbitros.asignar',
            'partidos.ver',
            'partidos.programar',
            'partidos.reprogramar',
            'cedulas.ver',
            'resultados.validar',
        ];
        $rolesCreados['Programador de Partidos']->syncPermissions($permisosProgramador);

        // Rol Capturista: captura de cedulas fisicas y resultados en sede de la liga (sin facultades sancionatorias ni de programacion)
        $permisosCapturista = [
            'temporadas.ver',
            'convocatorias.ver',
            'reglas_temporada.ver',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'jugadores.ver',
            'campos.ver',
            'partidos.ver',
            'cedulas.ver',
            'cedulas.capturar',
        ];
        $rolesCreados['Capturista']->syncPermissions($permisosCapturista);

        // Rol Arbitro en Campo: validacion en terreno, captura y firma de cedula digital (sin dictaminar sanciones)
        $permisosArbitroCampo = [
            'temporadas.ver',
            'convocatorias.ver',
            'categorias.ver',
            'equipos.ver',
            'jugadores.ver',
            'campos.ver',
            'arbitros.ver',
            'partidos.ver',
            'cedulas.ver',
            'cedulas.capturar',
            'cedulas.firmar',
            'disciplina.ver',
        ];
        $rolesCreados['Árbitro en Campo']->syncPermissions($permisosArbitroCampo);

        // Rol Comision Arbitral: administracion del padron de arbitros y colegios, y asignacion de ternas arbitrales
        $permisosComisionArbitral = [
            'temporadas.ver',
            'convocatorias.ver',
            'categorias.ver',
            'equipos.ver',
            'jugadores.ver',
            'campos.ver',
            'arbitros.ver',
            'arbitros.crear',
            'arbitros.editar',
            'arbitros.asignar',
            'partidos.ver',
            'cedulas.ver',
        ];
        $rolesCreados['Comisión Arbitral']->syncPermissions($permisosComisionArbitral);

        // Rol Tribunal de Penas: organo sancionador autonomo, resolucion de incidentes, expedientes y sanciones oficiales
        $permisosTribunalPenas = [
            'temporadas.ver',
            'convocatorias.ver',
            'categorias.ver',
            'equipos.ver',
            'jugadores.ver',
            'campos.ver',
            'arbitros.ver',
            'partidos.ver',
            'cedulas.ver',
            'disciplina.ver',
            'disciplina.expedientes',
            'disciplina.sancionar',
        ];
        $rolesCreados['Tribunal de Penas']->syncPermissions($permisosTribunalPenas);

        // 6. Asignacion preliminar para roles de Clubes y Jugadores (HU-073)
        $permisosClub = [
            'temporadas.ver',
            'convocatorias.ver',
            'categorias.ver',
            'clubes.ver',
            'campos.ver',
        ];
        $rolesCreados['Presidente del Club']->syncPermissions($permisosClub);
        $rolesCreados['Delegado del Club']->syncPermissions($permisosClub);

        // 7. Asignacion automatica de roles a los usuarios iniciales del sistema si existen
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
        if ($directivoUser && ! $directivoUser->hasRole('Presidente del Club')) {
            $directivoUser->assignRole('Presidente del Club');
        }

        $arbitroUser = User::where('email', 'arbitro@ligafutbol.com')->first();
        if ($arbitroUser && ! $arbitroUser->hasRole('Árbitro en Campo')) {
            $arbitroUser->assignRole('Árbitro en Campo');
        }

        $programadorUser = User::where('email', 'programador@ligafutbol.com')->first();
        if ($programadorUser && ! $programadorUser->hasRole('Programador de Partidos')) {
            $programadorUser->assignRole('Programador de Partidos');
        }

        $capturistaUser = User::where('email', 'capturista@ligafutbol.com')->first();
        if ($capturistaUser && ! $capturistaUser->hasRole('Capturista')) {
            $capturistaUser->assignRole('Capturista');
        }

        $comisionArbitralUser = User::where('email', 'comision.arbitral@ligafutbol.com')->first();
        if ($comisionArbitralUser && ! $comisionArbitralUser->hasRole('Comisión Arbitral')) {
            $comisionArbitralUser->assignRole('Comisión Arbitral');
        }

        $tribunalUser = User::where('email', 'tribunal@ligafutbol.com')->first();
        if ($tribunalUser && ! $tribunalUser->hasRole('Tribunal de Penas')) {
            $tribunalUser->assignRole('Tribunal de Penas');
        }
    }
}
