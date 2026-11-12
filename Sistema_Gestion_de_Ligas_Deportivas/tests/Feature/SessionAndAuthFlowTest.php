<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionAndAuthFlowTest extends TestCase
{
    // Manejo transaccional para revertir cambios sin purgar los datos sembrados
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Creacion de roles necesarios para evaluar la politica de expiracion diferenciada
        Role::firstOrCreate(['name' => 'Administrador General', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Presidente del Club', 'guard_name' => 'web']);
    }

    public function test_usuario_activo_puede_iniciar_sesion_satisfactoriamente(): void
    {
        $usuario = User::create([
            'name' => 'Usuario Activo',
            'email' => 'activo@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $usuario->assignRole('Administrador General');

        $respuesta = $this->post('/login', [
            'email' => 'activo@liga.com',
            'password' => 'secreto123',
        ]);

        // Verificacion de autenticacion exitosa y registro de la marca de actividad
        $this->assertAuthenticatedAs($usuario);
        $respuesta->assertRedirect('/admin/dashboard');
        $this->assertNotNull(session('last_activity_time'));
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion_y_recibe_error_en_espanol(): void
    {
        User::create([
            'name' => 'Usuario Inactivo',
            'email' => 'inactivo@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'inactivo',
            'rol' => 'Directivo Club',
        ]);

        $respuesta = $this->from('/login')->post('/login', [
            'email' => 'inactivo@liga.com',
            'password' => 'secreto123',
        ]);

        // Verificacion de rechazo de credenciales y mensaje en espanol
        $this->assertGuest();
        $respuesta->assertSessionHasErrors(['email']);
        $this->assertStringContainsString(
            'Su cuenta no se encuentra activa en el sistema',
            session('errors')->first('email')
        );
    }

    public function test_usuario_suspendido_no_puede_iniciar_sesion(): void
    {
        User::create([
            'name' => 'Usuario Suspendido',
            'email' => 'suspendido@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'suspendido',
            'rol' => 'Arbitro',
        ]);

        $respuesta = $this->from('/login')->post('/login', [
            'email' => 'suspendido@liga.com',
            'password' => 'secreto123',
        ]);

        // Comprobacion de que un usuario suspendido permanece como invitado
        $this->assertGuest();
        $respuesta->assertSessionHasErrors(['email']);
    }

    public function test_credenciales_con_contrasena_invalida_son_rechazadas(): void
    {
        User::create([
            'name' => 'Usuario Password',
            'email' => 'password@liga.com',
            'password' => Hash::make('correcto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);

        $respuesta = $this->from('/login')->post('/login', [
            'email' => 'password@liga.com',
            'password' => 'incorrecto999',
        ]);

        // Rechazo de password incorrecto
        $this->assertGuest();
        $respuesta->assertSessionHasErrors(['email']);
    }

    public function test_cierre_de_sesion_destruye_la_sesion_y_regenera_tokens(): void
    {
        $usuario = User::create([
            'name' => 'Usuario Logout',
            'email' => 'logout@liga.com',
            'password' => Hash::make('clave123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
        ]);

        $this->actingAs($usuario);
        session(['last_activity_time' => now()->timestamp]);

        $respuesta = $this->post('/logout');

        // Confirmacion de salida segura y limpieza de datos en sesion
        $this->assertGuest();
        $respuesta->assertRedirect(route('login'));
        $this->assertNull(session('last_activity_time'));
    }

    public function test_sesion_de_administrador_expira_tras_30_minutos_de_inactividad(): void
    {
        $admin = User::create([
            'name' => 'Administrador Test',
            'email' => 'admin_timeout@liga.com',
            'password' => Hash::make('admin123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $admin->assignRole('Administrador General');

        // Simulacion de ultima actividad registrada hace 31 minutos
        $tiempoInactivo = now()->subMinutes(31)->timestamp;

        $respuesta = $this->actingAs($admin)
            ->withSession(['last_activity_time' => $tiempoInactivo])
            ->get('/');

        // Bloqueo automatico por haber superado el umbral estricto de 30 minutos
        $this->assertGuest();
        $respuesta->assertRedirect(route('login'));
        $respuesta->assertSessionHas('status', 'Su sesion ha expirado por inactividad por motivos de seguridad.');
    }

    public function test_sesion_de_administrador_permanece_activa_dentro_de_los_30_minutos(): void
    {
        $admin = User::create([
            'name' => 'Administrador Activo',
            'email' => 'admin_activo@liga.com',
            'password' => Hash::make('admin123'),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
        ]);
        $admin->assignRole('Administrador General');

        // Ultima actividad hace 20 minutos dentro del rango permitido
        $tiempoReciente = now()->subMinutes(20)->timestamp;

        $respuesta = $this->actingAs($admin)
            ->withSession(['last_activity_time' => $tiempoReciente])
            ->get('/');

        // La sesion debe continuar activa y actualizarse a la fecha actual
        $this->assertAuthenticatedAs($admin);
        $respuesta->assertStatus(200);
        $this->assertGreaterThanOrEqual($tiempoReciente, session('last_activity_time'));
    }

    public function test_sesion_de_usuario_operativo_continua_activa_a_los_45_minutos(): void
    {
        $directivo = User::create([
            'name' => 'Directivo Test',
            'email' => 'directivo_timeout@liga.com',
            'password' => Hash::make('club123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
        ]);
        $directivo->assignRole('Presidente del Club');

        // Actividad hace 45 minutos lo cual excede los 30 minutos pero es valido para directivos
        $tiempo45Minutos = now()->subMinutes(45)->timestamp;

        $respuesta = $this->actingAs($directivo)
            ->withSession(['last_activity_time' => $tiempo45Minutos])
            ->get('/');

        // Debe mantenerse autenticado ya que el limite para usuarios operativos es de 8 horas
        $this->assertAuthenticatedAs($directivo);
        $respuesta->assertStatus(200);
    }

    public function test_sesion_de_usuario_operativo_expira_al_superar_las_8_horas(): void
    {
        $directivo = User::create([
            'name' => 'Directivo Expirado',
            'email' => 'directivo_8h@liga.com',
            'password' => Hash::make('club123'),
            'estatus' => 'activo',
            'rol' => 'Directivo Club',
        ]);
        $directivo->assignRole('Presidente del Club');

        // Inactividad de 8 horas y 5 minutos
        $tiempoExcedido = now()->subHours(8)->subMinutes(5)->timestamp;

        $respuesta = $this->actingAs($directivo)
            ->withSession(['last_activity_time' => $tiempoExcedido])
            ->get('/');

        // Al superar las 8 horas el usuario operativo es desconectado
        $this->assertGuest();
        $respuesta->assertRedirect(route('login'));
    }

    public function test_usuario_con_rol_tesorero_es_redirigido_al_modulo_de_cargos_sin_error_403(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $tesorero = User::create([
            'name' => 'Tesorero Municipal',
            'email' => 'tesorero@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Tesorero',
        ]);
        $tesorero->assignRole('Tesorero');

        $respuesta = $this->post('/login', [
            'email' => 'tesorero@liga.com',
            'password' => 'secreto123',
        ]);

        $this->assertAuthenticatedAs($tesorero);
        $respuesta->assertRedirect(route('cargos.index'));

        $vista = $this->actingAs($tesorero)->get(route('cargos.index'));
        $vista->assertStatus(200);
    }

    public function test_delegado_del_club_sin_club_es_redirigido_a_temporadas_index_con_200(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $delegadoSinClub = User::create([
            'name' => 'Delegado Sin Club',
            'email' => 'delegado_sin_club@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'club_id' => null,
            'rol' => 'Consultor',
        ]);
        $delegadoSinClub->assignRole('Delegado del Club');

        $respuesta = $this->post('/login', [
            'email' => 'delegado_sin_club@liga.com',
            'password' => 'secreto123',
        ]);

        $this->assertAuthenticatedAs($delegadoSinClub);
        $respuesta->assertRedirect(route('temporadas.index'));

        // Verificamos que temporadas.index cargue exitosamente con 200 para el Delegado
        $vista = $this->actingAs($delegadoSinClub)->get(route('temporadas.index'));
        $vista->assertStatus(200);

        // Verificamos que el intento de acceder a jugadores.create sea rechazado con 403 al no tener club asignado
        $intentoCrear = $this->actingAs($delegadoSinClub)->get(route('jugadores.create'));
        $intentoCrear->assertStatus(403);
    }

    public function test_delegado_del_club_con_club_es_redirigido_a_jugadores_create_con_200(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $club = Club::create(['nombre' => 'Club Deportivo Delegados']);

        $delegadoConClub = User::create([
            'name' => 'Delegado Con Club',
            'email' => 'delegado_con_club@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'club_id' => $club->id,
            'rol' => 'Consultor',
        ]);
        $delegadoConClub->assignRole('Delegado del Club');

        $respuesta = $this->post('/login', [
            'email' => 'delegado_con_club@liga.com',
            'password' => 'secreto123',
        ]);

        $this->assertAuthenticatedAs($delegadoConClub);
        $respuesta->assertRedirect(route('jugadores.create'));

        $vista = $this->actingAs($delegadoConClub)->get(route('jugadores.create'));
        $vista->assertStatus(200);
    }

    public function test_usuario_no_administrador_con_url_intended_hacia_admin_es_redirigido_a_su_modulo_sin_403(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $tesorero = User::create([
            'name' => 'Tesorero Intended',
            'email' => 'tesorero_intended@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);
        $tesorero->assignRole('Tesorero');

        // Simulamos que el usuario intentó acceder previamente a una ruta administrativa
        $respuesta = $this->withSession(['url.intended' => url('/admin/dashboard')])
            ->post('/login', [
                'email' => 'tesorero_intended@liga.com',
                'password' => 'secreto123',
            ]);

        $this->assertAuthenticatedAs($tesorero);
        // Debe ignorar /admin/dashboard y redirigir a cargos.index
        $respuesta->assertRedirect(route('cargos.index'));

        $vista = $this->actingAs($tesorero)->get(route('cargos.index'));
        $vista->assertStatus(200);
    }

    public function test_usuario_autenticado_que_visita_raiz_recibe_aviso_con_enlace_a_su_ruta_inicio(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $presidente = User::create([
            'name' => 'Presidente Liga',
            'email' => 'presidente_raiz@liga.com',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);
        $presidente->assignRole('Presidente');

        $respuesta = $this->actingAs($presidente)->get('/');

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Sesión activa como');
        $respuesta->assertSee(route('temporadas.index'));
        $respuesta->assertDontSee('/admin/dashboard');
    }

    #[DataProvider('proveedorRolesYDestinos')]
    public function test_redireccion_post_login_de_los_13_roles_hacia_modulo_autorizado_con_200(string $rol, string $nombreRutaEsperada, bool $requiereClub): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $club = $requiereClub ? Club::create(['nombre' => 'Club Test ' . $rol]) : null;

        $email = 'test_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $rol)) . '@liga.com';

        $usuario = User::create([
            'name' => 'Usuario ' . $rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'club_id' => $club?->id,
            'rol' => 'Consultor',
        ]);
        $usuario->assignRole($rol);

        $respuesta = $this->post('/login', [
            'email' => $email,
            'password' => 'secreto123',
        ]);

        $this->assertAuthenticatedAs($usuario);
        $destinoEsperado = route($nombreRutaEsperada);
        $respuesta->assertRedirect($destinoEsperado);

        $vista = $this->actingAs($usuario)->get($destinoEsperado);
        $vista->assertStatus(200);
    }

    public static function proveedorRolesYDestinos(): array
    {
        return [
            'Administrador General' => ['Administrador General', 'admin.dashboard', false],
            'Presidente' => ['Presidente', 'temporadas.index', false],
            'Secretario' => ['Secretario', 'personas.index', false],
            'Tesorero' => ['Tesorero', 'cargos.index', false],
            'Comision de la Rama Infantil' => ['Comision de la Rama Infantil', 'temporadas.index', false],
            'Programador de Partidos' => ['Programador de Partidos', 'canchas.index', false],
            'Capturista' => ['Capturista', 'temporadas.index', false],
            'Tribunal de Penas' => ['Tribunal de Penas', 'temporadas.index', false],
            'Comisión Arbitral' => ['Comisión Arbitral', 'arbitros.index', false],
            'Árbitro en Campo' => ['Árbitro en Campo', 'arbitros.index', false],
            'Presidente del Club' => ['Presidente del Club', 'temporadas.index', true],
            'Delegado del Club (con club)' => ['Delegado del Club', 'jugadores.create', true],
            'Jugador' => ['Jugador', 'jugadores.vincular.form', false],
        ];
    }
}
