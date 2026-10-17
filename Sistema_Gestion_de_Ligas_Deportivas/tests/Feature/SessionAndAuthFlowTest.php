<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
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
        Role::firstOrCreate(['name' => 'Directivo de Club', 'guard_name' => 'web']);
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

        $respuesta = $this->post('/login', [
            'email' => 'activo@liga.com',
            'password' => 'secreto123',
        ]);

        // Verificacion de autenticacion exitosa y registro de la marca de actividad
        $this->assertAuthenticatedAs($usuario);
        $respuesta->assertRedirect('/home');
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
        $this->assertGreaterThanOrEqual(now()->timestamp - 5, session('last_activity_time'));
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
        $directivo->assignRole('Directivo de Club');

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
        $directivo->assignRole('Directivo de Club');

        // Inactividad de 8 horas y 5 minutos
        $tiempoExcedido = now()->subHours(8)->subMinutes(5)->timestamp;

        $respuesta = $this->actingAs($directivo)
            ->withSession(['last_activity_time' => $tiempoExcedido])
            ->get('/');

        // Al superar las 8 horas el usuario operativo es desconectado
        $this->assertGuest();
        $respuesta->assertRedirect(route('login'));
    }
}
