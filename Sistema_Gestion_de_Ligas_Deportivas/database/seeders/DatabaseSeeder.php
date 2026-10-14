<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database based on BASE_DE_DATOS.md.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 1. ligas
        $ligaId = DB::table('ligas')->insertGetId([
            'nombre' => 'Liga Municipal de Futbol Amateur de Morelia, A.C.',
            'logo_url' => 'https://ligafutbolmorelia.com/img/logo_oficial.png',
            'estado' => 'activa',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. clubes
        $clubMoreliaId = DB::table('clubes')->insertGetId([
            'nombre' => 'Club Deportivo Morelia',
            'logo' => 'storage/clubes/morelia.png',
            'colores' => 'Rojo y Amarillo',
            'fecha_afiliacion' => '2010-08-15',
            'estado' => 'activo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $clubValladolidId = DB::table('clubes')->insertGetId([
            'nombre' => 'Atletico Valladolid',
            'logo' => 'storage/clubes/valladolid.png',
            'colores' => 'Azul y Blanco',
            'fecha_afiliacion' => '2015-09-01',
            'estado' => 'activo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $clubFuriaId = DB::table('clubes')->insertGetId([
            'nombre' => 'Furia Azul F.C.',
            'logo' => 'storage/clubes/furia.png',
            'colores' => 'Azul Marino',
            'fecha_afiliacion' => '2018-02-20',
            'estado' => 'activo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $clubHalconesId = DB::table('clubes')->insertGetId([
            'nombre' => 'Halcones de Morelia',
            'logo' => 'storage/clubes/halcones.png',
            'colores' => 'Verde y Negro',
            'fecha_afiliacion' => '2020-07-10',
            'estado' => 'activo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. usuarios
        $passwordHash = Hash::make('password123');

        $adminId = DB::table('usuarios')->insertGetId([
            'name' => 'Rafael Piedra Sanchez',
            'email' => 'admin@ligafutbol.com',
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
            'rol' => 'Administrador General',
            'club_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $presidenteId = DB::table('usuarios')->insertGetId([
            'name' => 'Presidente Liga Morelia',
            'email' => 'presidente@ligafutbol.com',
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
            'rol' => 'Presidente',
            'club_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $tesoreroId = DB::table('usuarios')->insertGetId([
            'name' => 'Tesorero Oficial',
            'email' => 'tesoreria@ligafutbol.com',
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
            'rol' => 'Tesorero',
            'club_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $directivoId = DB::table('usuarios')->insertGetId([
            'name' => 'Directivo Deportivo Morelia',
            'email' => 'directivo@moreliafc.com',
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
            'rol' => 'Directivo Club',
            'club_id' => $clubMoreliaId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $arbitroUserId = DB::table('usuarios')->insertGetId([
            'name' => 'Arbitro Juan Perez',
            'email' => 'arbitro@ligafutbol.com',
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
            'rol' => 'Arbitro',
            'club_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Registrar Roles de Spatie (RBAC) y asignar a usuarios
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesSpatie = [
            'Administrador General',
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comision Disciplinaria',
            'Directivo de Club',
            'Arbitro',
            'Consulta Publica',
        ];

        foreach ($rolesSpatie as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $adminUser = User::find($adminId);
        if ($adminUser) {
            $adminUser->assignRole('Administrador General');
        }

        $directivoUser = User::find($directivoId);
        if ($directivoUser) {
            $directivoUser->assignRole('Directivo de Club');
        }

        $arbitroUser = User::find($arbitroUserId);
        if ($arbitroUser) {
            $arbitroUser->assignRole('Arbitro');
        }

        // 4. auditoria_logs
        DB::table('auditoria_logs')->insert([
            [
                'usuario_id' => $adminId,
                'accion' => 'CREACION_SISTEMA',
                'tabla_afectada' => 'sistema',
                'registro_id' => 1,
                'valores_anteriores' => null,
                'valores_nuevos' => json_encode(['estado' => 'inicializado', 'version' => '1.0.0']),
                'ip' => '127.0.0.1',
                'created_at' => $now,
            ],
            [
                'usuario_id' => $adminId,
                'accion' => 'CONFIGURACION_TEMPORADA',
                'tabla_afectada' => 'temporadas',
                'registro_id' => 1,
                'valores_anteriores' => null,
                'valores_nuevos' => json_encode(['nombre' => 'Temporada 2026-2027']),
                'ip' => '127.0.0.1',
                'created_at' => $now,
            ]
        ]);

        // 5. asambleas
        DB::table('asambleas')->insert([
            'fecha' => '2026-09-20',
            'acta_pdf_url' => 'storage/asambleas/acta_ordinaria_2026_09_20.pdf',
            'acuerdos' => 'Se aprobo el inicio del torneo para el mes de octubre con 40 canchas habilitadas.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 6. comunicados_noticias
        DB::table('comunicados_noticias')->insert([
            'liga_id' => $ligaId,
            'titulo' => 'Apertura de Registros Temporada 2026-2027',
            'contenido' => 'Se informa a todos los clubes afiliados que el periodo de alta de jugadores y tarjetones ha comenzado oficialmente.',
            'estado' => 'Publicado',
            'publicado_en' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 7. registro_notificaciones
        DB::table('registro_notificaciones')->insert([
            'destinatario_email' => 'directivo@moreliafc.com',
            'asunto' => 'Confirmacion de inscripcion y registro',
            'tipo_aviso' => 'Afiliacion',
            'estado' => 'Enviado',
            'fecha_envio' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 8. temporadas
        $temporadaId = DB::table('temporadas')->insertGetId([
            'liga_id' => $ligaId,
            'nombre' => 'Temporada 2026-2027',
            'fecha_inicio' => '2026-10-12',
            'fecha_fin' => '2027-06-30',
            'estado' => 'activa',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 9. convocatorias
        DB::table('convocatorias')->insert([
            'temporada_id' => $temporadaId,
            'archivo_pdf' => 'storage/convocatorias/convocatoria_2026_2027.pdf',
            'reglas_versionadas' => json_encode([
                'version' => '1.0',
                'puntos_por_victoria' => 3,
                'puntos_por_empate' => 1,
                'puntos_por_derrota' => 0,
                'max_jugadores_roster' => 25,
                'defaults_para_baja' => 5,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 10. categorias
        $catEspecialId = DB::table('categorias')->insertGetId([
            'nombre' => 'Primera Especial',
            'edad_min' => 18,
            'edad_max' => 35,
            'jerarquia_salto' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $catFuerzaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Primera Fuerza',
            'edad_min' => 18,
            'edad_max' => 35,
            'jerarquia_salto' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $catJuvenilId = DB::table('categorias')->insertGetId([
            'nombre' => 'Juvenil A',
            'edad_min' => 14,
            'edad_max' => 16,
            'jerarquia_salto' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 11. grupos
        $grupo1Id = DB::table('grupos')->insertGetId([
            'categoria_id' => $catEspecialId,
            'temporada_id' => $temporadaId,
            'nombre' => 'Grupo Unico',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $grupo2Id = DB::table('grupos')->insertGetId([
            'categoria_id' => $catFuerzaId,
            'temporada_id' => $temporadaId,
            'nombre' => 'Grupo A',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 12. fases_torneo
        $faseRegularId = DB::table('fases_torneo')->insertGetId([
            'temporada_id' => $temporadaId,
            'tipo' => 'Regular',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $faseLiguillaId = DB::table('fases_torneo')->insertGetId([
            'temporada_id' => $temporadaId,
            'tipo' => 'Liguilla',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 13. directivos_club
        DB::table('directivos_club')->insert([
            [
                'club_id' => $clubMoreliaId,
                'nombre' => 'Roberto Carlos Gomez',
                'cargo' => 'Presidente de Club',
                'firma_digital_url' => 'storage/firmas/dir_morelia.png',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'club_id' => $clubValladolidId,
                'nombre' => 'Ernesto Gutierrez',
                'cargo' => 'Delegado Deportivo',
                'firma_digital_url' => 'storage/firmas/dir_valladolid.png',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ]);

        // 14. historial_ventas_club
        DB::table('historial_ventas_club')->insert([
            'club_id' => $clubHalconesId,
            'propietario_anterior' => 'Arturo Morales',
            'propietario_nuevo' => 'Consorcio Deportivo Michoacan',
            'carta_venta_pdf' => 'storage/ventas/carta_venta_halcones.pdf',
            'fecha_venta' => '2025-11-15',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 15. equipos
        $equipo1Id = DB::table('equipos')->insertGetId([
            'club_id' => $clubMoreliaId,
            'grupo_id' => $grupo1Id,
            'nombre' => 'Deportivo Morelia Titular',
            'partidos_default_acumulados' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $equipo2Id = DB::table('equipos')->insertGetId([
            'club_id' => $clubValladolidId,
            'grupo_id' => $grupo1Id,
            'nombre' => 'Atletico Valladolid Mayor',
            'partidos_default_acumulados' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $equipo3Id = DB::table('equipos')->insertGetId([
            'club_id' => $clubFuriaId,
            'grupo_id' => $grupo1Id,
            'nombre' => 'Furia Azul Primera',
            'partidos_default_acumulados' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $equipo4Id = DB::table('equipos')->insertGetId([
            'club_id' => $clubHalconesId,
            'grupo_id' => $grupo1Id,
            'nombre' => 'Halcones Especial',
            'partidos_default_acumulados' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 16. personas
        $p1Id = DB::table('personas')->insertGetId([
            'nombre' => 'Alejandro',
            'apellidos' => 'Hernandez Silva',
            'fecha_nacimiento' => '2001-04-12',
            'curp' => 'HESA010412HMNRLA01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $p2Id = DB::table('personas')->insertGetId([
            'nombre' => 'Luis Fernando',
            'apellidos' => 'Ramirez Mendoza',
            'fecha_nacimiento' => '1999-10-25',
            'curp' => 'RAML991025HMNMNL02',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $p3Id = DB::table('personas')->insertGetId([
            'nombre' => 'Diego',
            'apellidos' => 'Ortiz Alvarez',
            'fecha_nacimiento' => '2002-01-30',
            'curp' => 'ORAD020130HMNRTD03',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $pArbitroId = DB::table('personas')->insertGetId([
            'nombre' => 'Juan Manuel',
            'apellidos' => 'Perez Diaz',
            'fecha_nacimiento' => '1990-06-18',
            'curp' => 'PEDJ900618HMNRZP04',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 17. documentos_persona
        DB::table('documentos_persona')->insert([
            [
                'persona_id' => $p1Id,
                'tipo' => 'INE',
                'ruta_storage' => 'storage/privado/ine_p1.pdf',
                'validado_en' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'persona_id' => $p1Id,
                'tipo' => 'CURP',
                'ruta_storage' => 'storage/privado/curp_p1.pdf',
                'validado_en' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'persona_id' => $p1Id,
                'tipo' => 'Foto',
                'ruta_storage' => 'storage/privado/foto_p1.jpg',
                'validado_en' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 18. jugadores_equipos (Roster)
        $jugador1Id = DB::table('jugadores_equipos')->insertGetId([
            'persona_id' => $p1Id,
            'equipo_id' => $equipo1Id,
            'dorsal' => 10,
            'estado_elegibilidad' => 'Activo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $jugador2Id = DB::table('jugadores_equipos')->insertGetId([
            'persona_id' => $p2Id,
            'equipo_id' => $equipo2Id,
            'dorsal' => 9,
            'estado_elegibilidad' => 'Activo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $jugador3Id = DB::table('jugadores_equipos')->insertGetId([
            'persona_id' => $p3Id,
            'equipo_id' => $equipo1Id,
            'dorsal' => 7,
            'estado_elegibilidad' => 'Activo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 19. arbitros
        $arbitroId = DB::table('arbitros')->insertGetId([
            'persona_id' => $pArbitroId,
            'colegio_pertenencia' => 'Colegio de Arbitros de Michoacan A.C.',
            'tarifa_por_partido' => 450.00,
            'estado' => 'Activo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 20. canchas
        $cancha1Id = DB::table('canchas')->insertGetId([
            'nombre' => 'Cancha 1 Felix Cerda Loza',
            'direccion_ubicacion' => 'Unidad Deportiva Cuauhtemoc, Morelia',
            'capacidad_partidos_consecutivos' => 4,
            'estado' => 'Disponible',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $cancha2Id = DB::table('canchas')->insertGetId([
            'nombre' => 'Cancha 2 Furamo',
            'direccion_ubicacion' => 'Complejo Deportivo Furamo, Morelia',
            'capacidad_partidos_consecutivos' => 3,
            'estado' => 'Disponible',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 21. bloqueos_cancha
        DB::table('bloqueos_cancha')->insert([
            'cancha_id' => $cancha2Id,
            'fecha_inicio' => '2026-10-20 08:00:00',
            'fecha_fin' => '2026-10-20 18:00:00',
            'motivo' => 'Mantenimiento y resembrado de pasto',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 22. prestamos_cancha
        DB::table('prestamos_cancha')->insert([
            'cancha_id' => $cancha1Id,
            'club_solicitante_id' => $clubMoreliaId,
            'formato_prestamo_pdf' => 'storage/prestamos/prestamo_morelia_c1.pdf',
            'vigencia' => '2026-12-31',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 23. preferencias_equipo
        DB::table('preferencias_equipo')->insert([
            'equipo_id' => $equipo1Id,
            'cancha_id_preferida' => $cancha1Id,
            'dia_preferido' => 'Domingo',
            'hora_preferida' => '10:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 24. jornadas
        $jornada1Id = DB::table('jornadas')->insertGetId([
            'grupo_id' => $grupo1Id,
            'numero_jornada' => 1,
            'fecha_inicio' => '2026-10-18',
            'fecha_fin' => '2026-10-19',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 25. partidos
        $partido1Id = DB::table('partidos')->insertGetId([
            'jornada_id' => $jornada1Id,
            'equipo_local_id' => $equipo1Id,
            'equipo_visitante_id' => $equipo2Id,
            'cancha_id' => $cancha1Id,
            'arbitro_id' => $arbitroId,
            'fecha_partido' => '2026-10-18',
            'hora_partido' => '10:00:00',
            'estado' => 'finalizado',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $partido2Id = DB::table('partidos')->insertGetId([
            'jornada_id' => $jornada1Id,
            'equipo_local_id' => $equipo3Id,
            'equipo_visitante_id' => $equipo4Id,
            'cancha_id' => $cancha2Id,
            'arbitro_id' => $arbitroId,
            'fecha_partido' => '2026-10-18',
            'hora_partido' => '12:00:00',
            'estado' => 'programado',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 26. cedulas_arbitrales
        $cedula1Id = DB::table('cedulas_arbitrales')->insertGetId([
            'partido_id' => $partido1Id,
            'goles_local' => 2,
            'goles_visitante' => 1,
            'informe_arbitral' => 'Partido disputado con normalidad. Se suscito una amonestacion por falta temeraria en el segundo tiempo.',
            'firma_capitan_local' => 'data:image/png;base64,firmaLocalSimulada',
            'firma_capitan_visitante' => 'data:image/png;base64,firmaVisitaSimulada',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 27. eventos_partido
        DB::table('eventos_partido')->insert([
            [
                'cedula_id' => $cedula1Id,
                'jugador_id' => $jugador1Id,
                'tipo' => 'Gol',
                'minuto' => 23,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'cedula_id' => $cedula1Id,
                'jugador_id' => $jugador3Id,
                'tipo' => 'Gol',
                'minuto' => 67,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'cedula_id' => $cedula1Id,
                'jugador_id' => $jugador2Id,
                'tipo' => 'Gol',
                'minuto' => 84,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'cedula_id' => $cedula1Id,
                'jugador_id' => $jugador2Id,
                'tipo' => 'Amarilla',
                'minuto' => 70,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 28. catalogo_articulos
        $art1Id = DB::table('catalogo_articulos')->insertGetId([
            'codigo' => 'ART-12',
            'descripcion' => 'Juego brusco grave o conducta violenta sin balon',
            'castigo_base_partidos' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $art2Id = DB::table('catalogo_articulos')->insertGetId([
            'codigo' => 'ART-15',
            'descripcion' => 'Insultos u ofensas directas al cuerpo arbitral',
            'castigo_base_partidos' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 29. expedientes_disciplinarios
        $expedienteId = DB::table('expedientes_disciplinarios')->insertGetId([
            'cedula_id' => $cedula1Id,
            'partido_id' => $partido1Id,
            'articulo_id' => $art1Id,
            'estado' => 'Resuelto',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 30. sanciones
        $sancionId = DB::table('sanciones')->insertGetId([
            'expediente_id' => $expedienteId,
            'persona_id' => $p2Id,
            'descripcion_sancion' => 'Suspension por acumulacion y conducta antideportiva en Jornada 1',
            'partidos_suspendidos' => 1,
            'partidos_cumplidos' => 0,
            'estado' => 'Vigente',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 31. recursos_inconformidad
        DB::table('recursos_inconformidad')->insert([
            'sancion_id' => $sancionId,
            'club_apelante_id' => $clubValladolidId,
            'evidencia_pdf' => 'storage/apelaciones/recurso_sancion_1.pdf',
            'resolucion' => 'Pendiente de sesion del Tribunal de Penas',
            'estado' => 'Pendiente',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 32. tablas_posiciones
        DB::table('tablas_posiciones')->insert([
            [
                'grupo_id' => $grupo1Id,
                'equipo_id' => $equipo1Id,
                'pj' => 1,
                'pg' => 1,
                'pe' => 0,
                'pp' => 0,
                'gf' => 2,
                'gc' => 1,
                'dg' => 1,
                'puntos' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'grupo_id' => $grupo1Id,
                'equipo_id' => $equipo2Id,
                'pj' => 1,
                'pg' => 0,
                'pe' => 0,
                'pp' => 1,
                'gf' => 1,
                'gc' => 2,
                'dg' => -1,
                'puntos' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'grupo_id' => $grupo1Id,
                'equipo_id' => $equipo3Id,
                'pj' => 0,
                'pg' => 0,
                'pe' => 0,
                'pp' => 0,
                'gf' => 0,
                'gc' => 0,
                'dg' => 0,
                'puntos' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'grupo_id' => $grupo1Id,
                'equipo_id' => $equipo4Id,
                'pj' => 0,
                'pg' => 0,
                'pe' => 0,
                'pp' => 0,
                'gf' => 0,
                'gc' => 0,
                'dg' => 0,
                'puntos' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ]);

        // 33. llaves_liguilla
        DB::table('llaves_liguilla')->insert([
            'fase_id' => $faseLiguillaId,
            'equipo_local_id' => $equipo1Id,
            'equipo_visitante_id' => $equipo2Id,
            'marcador_global_local' => 0,
            'marcador_global_visitante' => 0,
            'avanza_equipo_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 34. cargos_financieros
        $cargoId = DB::table('cargos_financieros')->insertGetId([
            'club_id' => $clubMoreliaId,
            'equipo_id' => $equipo1Id,
            'persona_id' => null,
            'concepto' => 'Inscripcion',
            'monto' => 3500.00,
            'vencimiento' => '2026-10-31',
            'estado' => 'Pagado',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 35. pagos
        DB::table('pagos')->insert([
            'cargo_id' => $cargoId,
            'monto' => 3500.00,
            'metodo_pago' => 'Transferencia SPEI',
            'referencia' => 'SPEI-20261012-004812',
            'recibo_url' => 'storage/recibos/recibo_morelia_inscripcion.pdf',
            'validado_por_tesorero_id' => $tesoreroId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
