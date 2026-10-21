<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    // Sembrado y configuracion de los 13 roles oficiales y catalogo de permisos segun ERS Seccion 2.3 y Acuerdo PA-17
    public function run(): void
    {
        // Limpieza de memoria cache de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Definicion de los 13 roles oficiales de la Liga y alias de compatibilidad
        $roles = [
            // Categoria A: Administracion del Sistema
            'Administrador General',

            // Categoria B: Consejo Directivo y Gobernanza (HU-071)
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision de la Rama Infantil',

            // Categoria C: Operacion Deportiva, Arbitraje y Disciplina (HU-072)
            'Programador de Partidos',
            'Capturista',
            'Arbitro',
            'Arbitro en Campo',
            'Comision Arbitral',
            'Comision Disciplinaria',
            'Tribunal de Penas',

            // Categoria D: Clubes y Jugadores (HU-073)
            'Directivo de Club',
            'Presidente del Club',
            'Delegado del Club',
            'Jugador',

            // Compatibilidad Sprint 1
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

            // Modulo Cuentas de Usuario (Exclusivo Administrador General)
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

        // 3. Asignacion consolidada de todos los permisos al Administrador General
        $rolesCreados['Administrador General']->syncPermissions($catalogoPermisos);

        // 4. Asignacion granular para los 4 roles de Gobernanza (HU-071)
        // Rol Presidente: maxima autoridad institucional, supervision global, torneos y auditoria
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
            'categorias.crear',
            'categorias.editar',
            'clubes.ver',
            'clubes.crear',
            'clubes.editar',
            'equipos.ver',
            'jugadores.ver',
            'campos.ver',
            'campos.crear',
            'campos.editar',
            'campos.bloquear',
            'usuarios.ver',
            'auditoria.ver',
            'arbitros.ver',
            'partidos.ver',
            'cedulas.ver',
            'resultados.validar',
            'disciplina.ver',
            'finanzas.ver',
            'finanzas.reportes',
        ];
        $rolesCreados['Presidente']->syncPermissions($permisosPresidente);

        // Rol Secretario: fe publica, convocatorias, actas, inscripcion de clubes y padron de jugadores
        $permisosSecretario = [
            'gobernanza.panel',
            'ligas.ver',
            'temporadas.ver',
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',
            'reglas.ver',
            'categorias.ver',
            'categorias.crear',
            'categorias.editar',
            'clubes.ver',
            'clubes.crear',
            'clubes.editar',
            'equipos.ver',
            'equipos.crear',
            'equipos.editar',
            'jugadores.ver',
            'jugadores.crear',
            'jugadores.editar',
            'jugadores.transferir',
            'jugadores.validar_edad',
            'campos.ver',
            'usuarios.ver',
            'arbitros.ver',
            'arbitros.crear',
            'arbitros.editar',
            'partidos.ver',
            'cedulas.ver',
            'resultados.validar',
            'disciplina.ver',
        ];
        $rolesCreados['Secretario']->syncPermissions($permisosSecretario);

        // Rol Tesorero: administracion financiera, cargos, cobros, validacion de pagos y reportes
        // Estricto aislamiento en finanzas sin permisos operativos de temporadas ni canchas
        $permisosTesorero = [
            'gobernanza.panel',
            'finanzas.ver',
            'finanzas.cargos',
            'finanzas.pagos_validar',
            'finanzas.reportes',
        ];
        $rolesCreados['Tesorero']->syncPermissions($permisosTesorero);

        // Rol Comision de la Rama Infantil: categorias menores, control estricto de edad y acreditacion infantil
        $permisosComisionInfantil = [
            'gobernanza.panel',
            'temporadas.ver',
            'convocatorias.ver',
            'reglas.ver',
            'categorias.ver',
            'categorias.crear',
            'categorias.editar',
            'clubes.ver',
            'equipos.ver',
            'jugadores.ver',
            'jugadores.crear',
            'jugadores.editar',
            'jugadores.validar_edad',
            'campos.ver',
            'partidos.ver',
            'disciplina.ver',
        ];
        $rolesCreados['Comision de la Rama Infantil']->syncPermissions($permisosComisionInfantil);

        // 5. Asignacion a roles de Operacion Deportiva, Arbitraje y Disciplina (HU-072)
        // Programador de Partidos: logistica, canchas, bloqueos y rol de juegos
        $permisosProgramador = [
            'temporadas.ver',
            'convocatorias.ver',
            'reglas.ver',
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

        // Capturista: digitacion de cedulas fisicas y resultados
        $permisosCapturista = [
            'temporadas.ver',
            'convocatorias.ver',
            'reglas.ver',
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

        // Arbitro y Arbitro en Campo: validacion de alineaciones, firmas y cedula digital
        $permisosArbitro = [
            'temporadas.ver',
            'categorias.ver',
            'campos.ver',
            'arbitros.ver',
            'partidos.ver',
            'cedulas.ver',
            'cedulas.capturar',
            'cedulas.firmar',
            'disciplina.ver',
        ];
        $rolesCreados['Arbitro']->syncPermissions($permisosArbitro);
        $rolesCreados['Arbitro en Campo']->syncPermissions($permisosArbitro);

        // Comision Arbitral: padron de arbitros y asignacion de ternas
        $permisosComisionArbitral = [
            'temporadas.ver',
            'categorias.ver',
            'campos.ver',
            'arbitros.ver',
            'arbitros.crear',
            'arbitros.editar',
            'arbitros.asignar',
            'partidos.ver',
        ];
        $rolesCreados['Comision Arbitral']->syncPermissions($permisosComisionArbitral);

        // Comision Disciplinaria y Tribunal de Penas: dictamen de sanciones y expedientes
        $permisosDisciplinaria = [
            'temporadas.ver',
            'categorias.ver',
            'equipos.ver',
            'jugadores.ver',
            'arbitros.ver',
            'partidos.ver',
            'cedulas.ver',
            'disciplina.ver',
            'disciplina.expedientes',
            'disciplina.sancionar',
        ];
        $rolesCreados['Comision Disciplinaria']->syncPermissions($permisosDisciplinaria);
        $rolesCreados['Tribunal de Penas']->syncPermissions($permisosDisciplinaria);

        // 6. Asignacion a roles de Clubes y Jugadores (HU-073)
        // Directivo de Club y Presidente del Club: gestion institucional y financiera del club propio
        $permisosDirectivoClub = [
            'temporadas.ver',
            'convocatorias.ver',
            'reglas.ver',
            'categorias.ver',
            'clubes.ver',
            'clubes.editar',
            'equipos.ver',
            'equipos.crear',
            'equipos.editar',
            'jugadores.ver',
            'jugadores.crear',
            'jugadores.editar',
            'jugadores.transferir',
            'campos.ver',
            'partidos.ver',
            'cedulas.ver',
            'disciplina.ver',
            'finanzas.ver',
        ];
        $rolesCreados['Directivo de Club']->syncPermissions($permisosDirectivoClub);
        $rolesCreados['Presidente del Club']->syncPermissions($permisosDirectivoClub);

        // Delegado del Club: operaciones deportivas, alineaciones y altas de jugadores de su club
        $permisosDelegadoClub = [
            'temporadas.ver',
            'convocatorias.ver',
            'reglas.ver',
            'categorias.ver',
            'clubes.ver',
            'equipos.ver',
            'equipos.crear',
            'equipos.editar',
            'jugadores.ver',
            'jugadores.crear',
            'jugadores.editar',
            'jugadores.transferir',
            'campos.ver',
            'partidos.ver',
            'cedulas.ver',
            'cedulas.firmar',
            'disciplina.ver',
            'finanzas.ver',
        ];
        $rolesCreados['Delegado del Club']->syncPermissions($permisosDelegadoClub);

        // Jugador: consulta de su perfil, estadisticas, partidos y sanciones
        $permisosJugador = [
            'temporadas.ver',
            'categorias.ver',
            'equipos.ver',
            'jugadores.ver',
            'campos.ver',
            'partidos.ver',
            'cedulas.ver',
            'disciplina.ver',
        ];
        $rolesCreados['Jugador']->syncPermissions($permisosJugador);

        // Consulta Publica: lectura de calendarios, convocatorias y resultados
        $rolesCreados['Consulta Publica']->syncPermissions([
            'temporadas.ver',
            'convocatorias.ver',
        ]);

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
        if ($directivoUser && ! $directivoUser->hasRole('Directivo de Club')) {
            $directivoUser->assignRole('Directivo de Club');
        }

        $arbitroUser = User::where('email', 'arbitro@ligafutbol.com')->first();
        if ($arbitroUser && ! $arbitroUser->hasRole('Arbitro')) {
            $arbitroUser->assignRole('Arbitro');
        }
    }
}
