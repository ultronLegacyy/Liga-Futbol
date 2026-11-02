<?php

namespace Tests\Feature;

use App\Mail\CorreoDelSistema;
use App\Models\User;
use App\Rules\RNF26Password;
use App\Services\BruteForceProtectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class BruteForceAndSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected BruteForceProtectionService $bruteForce;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bruteForce = app(BruteForceProtectionService::class);
        RateLimiter::clear('login');
        RNF26Password::resetDictionaryCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RNF26Password::resetDictionaryCache();
        parent::tearDown();
    }

    /**
     * CA1: De 1 a 4 intentos fallidos consecutivos se rechazan advirtiendo los intentos restantes
     * sin bloquear la cuenta.
     */
    public function test_intentos_fallidos_del_1_al_4_no_bloquean_la_cuenta_y_muestran_advertencia(): void
    {
        User::factory()->create([
            'email' => 'jugador.prueba@liga.com',
            'password' => Hash::make('ContrasenaValida123!'),
            'estatus' => 'activo',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $response = $this->from('/login')->post('/login', [
                'email' => 'jugador.prueba@liga.com',
                'password' => 'clave_incorrecta_'.$i,
            ]);

            $this->assertGuest();
            $response->assertSessionHasErrors(['email']);

            $restantes = 5 - $i;
            $mensaje = session('errors')->first('email');
            $this->assertStringContainsString("Le quedan {$restantes} intento(s)", $mensaje);

            $this->assertFalse($this->bruteForce->isLocked('jugador.prueba@liga.com'));
            $this->assertEquals($i, $this->bruteForce->getAttempts('jugador.prueba@liga.com'));
        }
    }

    /**
     * CA2: El quinto intento fallido consecutivo activa el bloqueo temporal por 15 minutos (900s).
     */
    public function test_quinto_intento_fallido_bloquea_cuenta_por_15_minutos(): void
    {
        User::factory()->create([
            'email' => 'bloqueo.prueba@liga.com',
            'password' => Hash::make('ContrasenaValida123!'),
            'estatus' => 'activo',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $this->post('/login', [
                'email' => 'bloqueo.prueba@liga.com',
                'password' => 'clave_erronea_'.$i,
            ]);
        }

        $response5 = $this->post('/login', [
            'email' => 'bloqueo.prueba@liga.com',
            'password' => 'clave_erronea_5',
        ]);

        $this->assertGuest();
        $response5->assertSessionHasErrors(['email']);

        $mensaje = session('errors')->first('email');
        $this->assertStringContainsString('Su cuenta ha sido bloqueada temporalmente por 15 minutos', $mensaje);
        $this->assertTrue($this->bruteForce->isLocked('bloqueo.prueba@liga.com'));

        $restantesSegundos = $this->bruteForce->getLockoutRemainingSeconds('bloqueo.prueba@liga.com');
        $this->assertGreaterThan(0, $restantesSegundos);
        $this->assertLessThanOrEqual(900, $restantesSegundos);
    }

    /**
     * CA3: Al bloquearse la cuenta se envía una notificación inmediata por correo al titular con los datos del evento.
     */
    public function test_envia_correo_de_alerta_inmediato_al_titular_al_alcanzar_quinto_intento(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Roberto Carlos',
            'email' => 'roberto.carlos@liga.com',
            'password' => Hash::make('ContrasenaValida123!'),
            'estatus' => 'activo',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $this->post('/login', [
                'email' => 'roberto.carlos@liga.com',
                'password' => 'clave_invalida',
            ]);
        }

        Mail::assertNothingSent();

        // 5to intento
        $this->post('/login', [
            'email' => 'roberto.carlos@liga.com',
            'password' => 'clave_invalida',
        ], ['REMOTE_ADDR' => '203.0.113.195']);

        Mail::assertSent(CorreoDelSistema::class, function (CorreoDelSistema $mail) use ($user) {
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertEquals('Aviso de seguridad: Cuenta bloqueada temporalmente por intentos fallidos', $mail->asunto);
            $this->assertStringContainsString('Roberto Carlos', $mail->mensaje);
            $this->assertStringContainsString('203.0.113.195', $mail->mensaje);
            $this->assertStringContainsString('15 minutos', $mail->mensaje);

            return true;
        });
    }

    /**
     * CA4: Durante el período de 15 minutos de bloqueo, cualquier intento posterior con credenciales
     * incorrectas O CORRECTAS es rechazado.
     */
    public function test_durante_bloqueo_activo_se_rechazan_credenciales_correctas_e_incorrectas(): void
    {
        User::factory()->create([
            'email' => 'victima.fuerzabruta@liga.com',
            'password' => Hash::make('MiContrasenaReal123!'),
            'estatus' => 'activo',
        ]);

        // Provocar 5 intentos fallidos
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'email' => 'victima.fuerzabruta@liga.com',
                'password' => 'password_atacante_'.$i,
            ]);
        }

        $this->assertTrue($this->bruteForce->isLocked('victima.fuerzabruta@liga.com'));

        // Intento con contraseña incorrecta durante el bloqueo
        $respInvalida = $this->post('/login', [
            'email' => 'victima.fuerzabruta@liga.com',
            'password' => 'otro_intento_invalido',
        ]);
        $this->assertGuest();
        $respInvalida->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Su cuenta ha sido bloqueada temporalmente por 15 minutos', session('errors')->first('email'));

        // Intento con la contraseña CORRECTA legítima durante el bloqueo
        $respValida = $this->post('/login', [
            'email' => 'victima.fuerzabruta@liga.com',
            'password' => 'MiContrasenaReal123!',
        ]);
        $this->assertGuest();
        $respValida->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Su cuenta ha sido bloqueada temporalmente por 15 minutos', session('errors')->first('email'));
    }

    /**
     * CA5: Transcurrido el período de 15 minutos, la cuenta se desbloquea automáticamente sin intervención manual.
     */
    public function test_cuenta_se_desbloquea_automaticamente_tras_15_minutos(): void
    {
        $ahora = Carbon::create(2026, 11, 1, 10, 0, 0);
        Carbon::setTestNow($ahora);

        $user = User::factory()->create([
            'email' => 'recuperacion.auto@liga.com',
            'password' => Hash::make('ContrasenaExacta123!'),
            'estatus' => 'activo',
        ]);

        // 5 intentos fallidos a las 10:00:00
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'email' => 'recuperacion.auto@liga.com',
                'password' => 'error_'.$i,
            ]);
        }
        $this->assertTrue($this->bruteForce->isLocked('recuperacion.auto@liga.com'));

        // A las 10:14:00 (14 minutos después) sigue bloqueada
        Carbon::setTestNow($ahora->copy()->addMinutes(14));
        $this->assertTrue($this->bruteForce->isLocked('recuperacion.auto@liga.com'));

        // A las 10:15:01 (15 minutos y 1 segundo después), el bloqueo expira automáticamente
        Carbon::setTestNow($ahora->copy()->addMinutes(15)->addSecond());
        $this->assertFalse($this->bruteForce->isLocked('recuperacion.auto@liga.com'));

        // El usuario puede iniciar sesión exitosamente
        $resp = $this->post('/login', [
            'email' => 'recuperacion.auto@liga.com',
            'password' => 'ContrasenaExacta123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $resp->assertRedirect(config('fortify.home'));
    }

    /**
     * CA6: Si el usuario ingresa las credenciales correctas antes de 5 intentos, el contador se reinicia a 0.
     */
    public function test_login_exitoso_antes_de_5_intentos_reinicia_contador_a_cero(): void
    {
        $user = User::factory()->create([
            'email' => 'reinicio.contador@liga.com',
            'password' => Hash::make('ClaveSegura12345!'),
            'estatus' => 'activo',
        ]);

        // 3 intentos fallidos
        for ($i = 1; $i <= 3; $i++) {
            $this->post('/login', [
                'email' => 'reinicio.contador@liga.com',
                'password' => 'erronea_'.$i,
            ]);
        }
        $this->assertEquals(3, $this->bruteForce->getAttempts('reinicio.contador@liga.com'));

        // 4to intento con contraseña CORRECTA
        $respCorrecto = $this->post('/login', [
            'email' => 'reinicio.contador@liga.com',
            'password' => 'ClaveSegura12345!',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertEquals(0, $this->bruteForce->getAttempts('reinicio.contador@liga.com'));
        $this->assertFalse($this->bruteForce->isLocked('reinicio.contador@liga.com'));

        // Cierre de sesión y nuevo intento fallido: debe reportar que quedan 4 intentos, no 1
        $this->post('/logout');
        $this->assertGuest();

        $respFalla = $this->from('/login')->post('/login', [
            'email' => 'reinicio.contador@liga.com',
            'password' => 'otra_falla',
        ]);
        $respFalla->assertSessionHasErrors(['email']);

        $errores = session('errors');
        $mensaje = $errores instanceof \Illuminate\Support\ViewErrorBag
            ? $errores->first('email')
            : (is_array($errores) && isset($errores['email']) ? (is_array($errores['email']) ? $errores['email'][0] : $errores['email']) : '');

        $this->assertStringContainsString('Le quedan 4 intento(s)', $mensaje);
    }

    /**
     * CA7: Límite de peticiones de login por dirección IP (rate limiting de red) retorna HTTP 429 al superarse.
     */
    public function test_rate_limiting_por_ip_retorna_429_al_superar_limite(): void
    {
        $ip = '198.51.100.50';

        // 10 peticiones permitidas por minuto por IP
        for ($i = 1; $i <= 10; $i++) {
            $resp = $this->call('POST', '/login', [
                'email' => "usuario{$i}@liga.com",
                'password' => 'intento',
            ], [], [], ['REMOTE_ADDR' => $ip]);

            $this->assertNotEquals(429, $resp->getStatusCode());
        }

        // La 11va petición en la misma ventana de 1 minuto desde la misma IP recibe HTTP 429
        $resp11 = $this->call('POST', '/login', [
            'email' => 'usuario11@liga.com',
            'password' => 'intento',
        ], [], [], ['REMOTE_ADDR' => $ip]);

        $this->assertEquals(429, $resp11->getStatusCode());
        $this->assertStringContainsString(
            'Demasiadas solicitudes de inicio de sesión desde esta dirección IP',
            $resp11->getContent()
        );
    }

    /**
     * CA8: RNF-26 exige longitud mínima de 15 caracteres sin 2FA y rechaza contraseñas menores a 15 o mayores a 64.
     */
    public function test_rnf26_politica_de_longitud_sin_2fa(): void
    {
        $regla = new RNF26Password(null);

        // 14 caracteres (rechazado)
        $errores14 = [];
        $regla->validate('password', Str::random(14), function ($msg) use (&$errores14) {
            $errores14[] = $msg;
        });
        $this->assertContains('La contraseña debe tener al menos 15 caracteres.', $errores14);

        // 15 caracteres (aceptado)
        $errores15 = [];
        $regla->validate('password', 'ClaveSegura12345!', function ($msg) use (&$errores15) {
            $errores15[] = $msg;
        });
        $this->assertEmpty($errores15);

        // 64 caracteres (aceptado)
        $errores64 = [];
        $regla->validate('password', str_repeat('a', 64), function ($msg) use (&$errores64) {
            $errores64[] = $msg;
        });
        $this->assertEmpty($errores64);

        // 65 caracteres (rechazado)
        $errores65 = [];
        $regla->validate('password', str_repeat('a', 65), function ($msg) use (&$errores65) {
            $errores65[] = $msg;
        });
        $this->assertContains('La contraseña no debe exceder los 64 caracteres.', $errores65);
    }

    /**
     * CA9: RNF-26 permite contraseñas de al menos 8 caracteres cuando el usuario tiene 2FA activo.
     */
    public function test_rnf26_politica_de_longitud_con_2fa_activo(): void
    {
        $userCon2FA = User::factory()->make([
            'two_factor_secret' => 'secreto_2fa_configurado',
            'two_factor_confirmed_at' => now(),
        ]);

        $regla = new RNF26Password($userCon2FA);

        // 7 caracteres (rechazado)
        $errores7 = [];
        $regla->validate('password', '1234567', function ($msg) use (&$errores7) {
            $errores7[] = $msg;
        });
        $this->assertContains('Como tienes doble factor activo, la contraseña debe tener al menos 8 caracteres.', $errores7);

        // 8 caracteres (aceptado)
        $errores8 = [];
        $regla->validate('password', 'Abcd123!', function ($msg) use (&$errores8) {
            $errores8[] = $msg;
        });
        $this->assertEmpty($errores8);
    }

    /**
     * CA10: RNF-26 verifica de manera offline que la contraseña no pertenezca a filtraciones conocidas.
     */
    public function test_rnf26_rechaza_contrasenas_comprometidas_offline(): void
    {
        $regla = new RNF26Password(null);

        // Contraseñas conocidas en filtraciones públicas
        $comprometidas = [
            '123456789012345',
            'passwordpassword',
            'supersecretpassword',
            'correcthorsebatterystaple',
            'administrador123',
            'footballfootball',
        ];

        foreach ($comprometidas as $pwd) {
            $errores = [];
            $regla->validate('password', $pwd, function ($msg) use (&$errores) {
                $errores[] = $msg;
            });
            $this->assertContains(
                'Esta contraseña ha aparecido en filtraciones de seguridad conocidas. Elige una más segura.',
                $errores,
                "Falló al rechazar contraseña comprometida: {$pwd}"
            );
        }

        // Contraseña agregada dinámicamente
        RNF26Password::addCustomCompromised('ClaveFiltradaEspecial123');
        $erroresCustom = [];
        $regla->validate('password', 'ClaveFiltradaEspecial123', function ($msg) use (&$erroresCustom) {
            $erroresCustom[] = $msg;
        });
        $this->assertContains(
            'Esta contraseña ha aparecido en filtraciones de seguridad conocidas. Elige una más segura.',
            $erroresCustom
        );

        // Contraseña legítima y segura
        $erroresSegura = [];
        $regla->validate('password', 'Xk9#mP$2vL@8qR!wZ7', function ($msg) use (&$erroresSegura) {
            $erroresSegura[] = $msg;
        });
        $this->assertEmpty($erroresSegura);
    }

    /**
     * CA11: RNF-26 especifica almacenamiento con Argon2id o bcrypt con costo >= 12.
     */
    public function test_rnf26_configuracion_de_hash_cumple_estandares(): void
    {
        $driver = config('hashing.driver');
        $this->assertContains($driver, ['argon2id', 'bcrypt']);

        // Verificar que el archivo de configuración base define rounds por defecto de 12
        $configContent = (string) file_get_contents(config_path('hashing.php'));
        $this->assertStringContainsString("env('BCRYPT_ROUNDS', '12')", $configContent);

        // Verificación del hash generado con costo 12 conforme a RNF-26
        $hashCosto12 = Hash::driver('bcrypt')->make('ContrasenaPrueba123!', ['rounds' => 12]);
        $info = password_get_info($hashCosto12);
        $this->assertEquals(12, $info['options']['cost']);
    }

    /**
     * Mitigación de Collation en MariaDB (RNF-28):
     * Las variantes de mayúsculas o acentos del correo deben compartir el mismo contador y bloqueo.
     */
    public function test_bloqueo_fuerza_bruta_no_se_evade_con_variantes_de_correo_por_collation(): void
    {
        User::factory()->create([
            'email' => 'admin@liga.com',
            'password' => Hash::make('SuperPasswordValido2026!'),
            'estatus' => 'activo',
        ]);

        $variantes = [
            'ADMIN@LIGA.COM',
            'Admin@Liga.Com',
            'admin@liga.com',
            'ADMIN@liga.com',
        ];

        foreach ($variantes as $i => $variante) {
            $this->post('/login', [
                'email' => $variante,
                'password' => 'error_'.$i,
            ]);
        }

        $this->assertEquals(4, $this->bruteForce->getAttempts('admin@liga.com'));
        $this->assertEquals(4, $this->bruteForce->getAttempts('ADMIN@LIGA.COM'));

        // 5to intento con otra variante detona el bloqueo para todas las representaciones
        $response5 = $this->post('/login', [
            'email' => 'Admin@LIGA.com',
            'password' => 'error_5',
        ]);

        $this->assertGuest();
        $response5->assertSessionHasErrors(['email']);
        $this->assertTrue($this->bruteForce->isLocked('admin@liga.com'));
        $this->assertTrue($this->bruteForce->isLocked('ADMIN@LIGA.COM'));
        $this->assertTrue($this->bruteForce->isLocked('Admin@LIGA.com'));
    }

    /**
     * Fallo de SMTP no genera 500 ni oracle de enumeración (CA1 / RNF-28).
     */
    public function test_fallo_smtp_al_bloquear_cuenta_no_genera_error_500_y_mantiene_bloqueo(): void
    {
        User::factory()->create([
            'email' => 'smtp.fallo@liga.com',
            'password' => Hash::make('ClaveSegura12345!'),
            'estatus' => 'activo',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $this->post('/login', [
                'email' => 'smtp.fallo@liga.com',
                'password' => 'erronea_'.$i,
            ]);
        }

        // Simulamos fallo en transporte SMTP al enviar correo
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Connection to SMTP server failed'));

        // 5to intento
        $response = $this->post('/login', [
            'email' => 'smtp.fallo@liga.com',
            'password' => 'erronea_5',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Su cuenta ha sido bloqueada temporalmente por 15 minutos', session('errors')->first('email'));
        $this->assertTrue($this->bruteForce->isLocked('smtp.fallo@liga.com'));
    }

    /**
     * Prevención de TypeError con password como array.
     */
    public function test_login_con_password_como_array_no_genera_error_500(): void
    {
        User::factory()->create([
            'email' => 'array.test@liga.com',
            'password' => Hash::make('ClaveSegura12345!'),
            'estatus' => 'activo',
        ]);

        $response = $this->post('/login', [
            'email' => 'array.test@liga.com',
            'password' => ['invalido'],
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['password']);
    }

    /**
     * RNF26Password rechaza valores que no son cadenas de texto sin lanzar TypeError.
     */
    public function test_rnf26_password_rule_rechaza_array_sin_lanzar_type_error(): void
    {
        $regla = new RNF26Password();
        $errores = [];

        $regla->validate('password', ['invalido'], function ($msg) use (&$errores) {
            $errores[] = $msg;
        });

        $this->assertContains('La contraseña debe ser una cadena de texto.', $errores);
    }

    /**
     * Evasión de 15 caracteres impedida: 2FA no confirmado exige 15 caracteres.
     */
    public function test_usuario_con_2fa_no_confirmado_no_puede_usar_contrasena_de_8_caracteres(): void
    {
        // Usuario con secreto generado pero sin confirmar (two_factor_confirmed_at es null)
        $userSinConfirmar = User::factory()->create([
            'two_factor_secret' => 'secreto_sin_confirmar',
            'two_factor_confirmed_at' => null,
        ]);

        $regla = new RNF26Password($userSinConfirmar);
        $errores = [];

        // Intento con contraseña de 8 caracteres (debe ser rechazada porque no está confirmado)
        $regla->validate('password', 'Valida8!', function ($msg) use (&$errores) {
            $errores[] = $msg;
        });

        $this->assertContains('La contraseña debe tener al menos 15 caracteres.', $errores);

        // Usuario con 2FA confirmado
        $userConfirmado = User::factory()->create([
            'two_factor_secret' => 'secreto_confirmado',
            'two_factor_confirmed_at' => now(),
        ]);

        $reglaConfirmado = new RNF26Password($userConfirmado);
        $erroresConfirmado = [];

        $reglaConfirmado->validate('password', 'Valida8!', function ($msg) use (&$erroresConfirmado) {
            $erroresConfirmado[] = $msg;
        });

        $this->assertEmpty($erroresConfirmado);
    }

    /**
     * El aviso de bloqueo se envía ÚNICAMENTE en el intento 5 (no en 6, 7 ni 8).
     */
    public function test_aviso_bloqueo_se_envia_solo_en_el_quinto_intento_no_en_posteriores(): void
    {
        Mail::fake();

        User::factory()->create([
            'name' => 'Usuario Concurrente',
            'email' => 'concurrente@liga.com',
            'password' => Hash::make('ContrasenaValida123!'),
            'estatus' => 'activo',
        ]);

        // 5 intentos fallidos
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'email' => 'concurrente@liga.com',
                'password' => 'error_'.$i,
            ]);
        }

        Mail::assertSent(CorreoDelSistema::class, 1);

        // Intentos posteriores durante el bloqueo
        for ($i = 6; $i <= 8; $i++) {
            $this->post('/login', [
                'email' => 'concurrente@liga.com',
                'password' => 'error_'.$i,
            ]);
        }

        Mail::assertSent(CorreoDelSistema::class, 1);
    }

    /**
     * La plantilla de correo de bloqueo renderiza correctamente sin errores de variables ni vista.
     */
    public function test_aviso_bloqueo_renderiza_vista_sin_errores(): void
    {
        $mailable = new CorreoDelSistema(
            asunto: 'Aviso de seguridad: Cuenta bloqueada temporalmente por intentos fallidos',
            mensaje: "Hola Titular de Prueba,\n\nSe ha bloqueado temporalmente el acceso a tu cuenta por 15 minutos.",
        );

        $html = $mailable->render();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Aviso de seguridad: Cuenta bloqueada', $html);
        $this->assertStringContainsString('Hola Titular de Prueba', $html);
        $this->assertStringContainsString('15 minutos', $html);
    }

    /**
     * Integración en formulario de registro (/register):
     * Valida longitud mínima de 15 caracteres y rechazo de contraseñas comprometidas.
     */
    public function test_registro_aplica_regla_rnf26_y_rechaza_contrasenas_invalidas(): void
    {
        // 1. Menos de 15 caracteres
        $respCorta = $this->post('/register', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo1@liga.com',
            'password' => 'Corta1234!',
            'password_confirmation' => 'Corta1234!',
        ]);
        $respCorta->assertSessionHasErrors(['password']);

        // 2. Contraseña comprometida en diccionario
        $respComprometida = $this->post('/register', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo2@liga.com',
            'password' => 'supersecretpassword',
            'password_confirmation' => 'supersecretpassword',
        ]);
        $respComprometida->assertSessionHasErrors(['password']);

        // 3. Contraseña válida de 15+ caracteres
        $respValida = $this->post('/register', [
            'name' => 'Usuario Registrado',
            'email' => 'registrado@liga.com',
            'password' => 'ContrasenaSeguraValida2026!',
            'password_confirmation' => 'ContrasenaSeguraValida2026!',
        ]);
        $respValida->assertRedirect(config('fortify.home'));

        $usuario = User::where('email', 'registrado@liga.com')->first();
        $this->assertNotNull($usuario);

        // Verificación de almacenamiento conforme a RNF-26 (Argon2id o bcrypt costo >= 12)
        $hashInfo = password_get_info($usuario->password);
        $supportsArgon = defined('PASSWORD_ARGON2ID') && in_array(PASSWORD_ARGON2ID, password_algos());
        if ($supportsArgon) {
            $this->assertSame('argon2id', $hashInfo['algoName']);
        } else {
            $this->assertContains($hashInfo['algoName'], ['argon2id', 'bcrypt', '2y']);
        }
    }

    /**
     * Integración en actualización de contraseña (/user/password):
     * Usuario sin 2FA requiere 15 caracteres; usuario con 2FA confirmado permite 8 caracteres.
     */
    public function test_actualizacion_contrasena_en_perfil_aplica_politica_segun_2fa(): void
    {
        // Usuario sin 2FA
        $userSin2FA = User::factory()->create([
            'email' => 'perfil.sin2fa@liga.com',
            'password' => Hash::make('ClaveActualSegura2026!'),
            'estatus' => 'activo',
        ]);

        $respCorta = $this->actingAs($userSin2FA)->put('/user/password', [
            'current_password' => 'ClaveActualSegura2026!',
            'password' => 'Corta8!x',
            'password_confirmation' => 'Corta8!x',
        ]);
        $respCorta->assertSessionHasErrors('password', null, 'updatePassword');

        // Usuario con 2FA confirmado
        $userCon2FA = User::factory()->create([
            'email' => 'perfil.con2fa@liga.com',
            'password' => Hash::make('ClaveActualSegura2026!'),
            'two_factor_secret' => 'secreto_2fa_confirmado',
            'two_factor_confirmed_at' => now(),
            'estatus' => 'activo',
        ]);

        $respOk = $this->actingAs($userCon2FA)->put('/user/password', [
            'current_password' => 'ClaveActualSegura2026!',
            'password' => 'Valida8!x',
            'password_confirmation' => 'Valida8!x',
        ]);
        $respOk->assertSessionHasNoErrors();

        $userCon2FA->refresh();
        $this->assertTrue(Hash::check('Valida8!x', $userCon2FA->password));
    }

    /**
     * Independencia del bloqueo:
     * El bloqueo sobre una cuenta no afecta a otra cuenta independiente.
     */
    public function test_bloqueo_es_independiente_por_cuenta(): void
    {
        $userA = User::factory()->create([
            'email' => 'cuentaA@liga.com',
            'password' => Hash::make('ClaveCuentaA2026!'),
            'estatus' => 'activo',
        ]);

        $userB = User::factory()->create([
            'email' => 'cuentaB@liga.com',
            'password' => Hash::make('ClaveCuentaB2026!'),
            'estatus' => 'activo',
        ]);

        // Bloquear cuenta A
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'email' => 'cuentaA@liga.com',
                'password' => 'error_'.$i,
            ]);
        }
        $this->assertTrue($this->bruteForce->isLocked('cuentaA@liga.com'));

        // Cuenta B NO está bloqueada y puede autenticarse perfectamente
        $this->assertFalse($this->bruteForce->isLocked('cuentaB@liga.com'));

        $respB = $this->post('/login', [
            'email' => 'cuentaB@liga.com',
            'password' => 'ClaveCuentaB2026!',
        ]);
        $this->assertAuthenticatedAs($userB);
        $respB->assertRedirect(config('fortify.home'));
    }
}
