<?php

namespace Tests\Feature;

use App\Mail\CorreoDelSistema;
use App\Models\AuditoriaLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class RecuperacionContrasenaTest extends TestCase
{
    // La auditoría no se puede borrar, así que nada debe confirmarse
    use DatabaseTransactions;

    private const CORREO = 'delegado.recuperacion@liga.test';

    private const CLAVE_ANTERIOR = 'Clave-Anterior-De-Prueba-1';

    private const CLAVE_NUEVA = 'Clave-Nueva-De-Prueba-2026';

    private const MENSAJE_SOLICITUD = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña en unos minutos.';

    // CA1: con un correo registrado se envía un enlace de un solo uso, en HTML y en texto
    public function test_con_un_correo_registrado_envia_el_enlace_en_html_y_en_texto(): void
    {
        Mail::fake();
        $usuario = $this->crearUsuario();

        // El correo se normaliza a minúsculas, igual que en el login
        $this->post(route('password.email'), ['email' => strtoupper(self::CORREO)])
            ->assertRedirect()
            ->assertSessionHas('status', self::MENSAJE_SOLICITUD);

        Mail::assertSentCount(1);
        $correo = $this->correoEnviado();

        $this->assertTrue($correo->hasTo($usuario->email));
        $this->assertSame('Recupera tu contraseña del SGLD', $correo->asunto);
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/').'/reset-password/', (string) $correo->enlaceUrl);
        $correo->assertSeeInHtml((string) $correo->enlaceUrl);
        $correo->assertSeeInHtml('Restablecer mi contraseña');
        $correo->assertSeeInText('Restablecer mi contraseña: '.$correo->enlaceUrl);
        $correo->assertSeeInText('vence en 60 minutos y solo se puede usar una vez');
    }

    public function test_con_un_correo_no_registrado_responde_lo_mismo_sin_enviar_nada(): void
    {
        Mail::fake();

        $this->post(route('password.email'), ['email' => 'nadie@liga.test'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', self::MENSAJE_SOLICITUD);

        Mail::assertNothingSent();
    }

    public function test_una_segunda_solicitud_inmediata_responde_lo_mismo_sin_enviar_otro_correo(): void
    {
        Mail::fake();
        $this->crearUsuario();

        $this->post(route('password.email'), ['email' => self::CORREO])->assertSessionHas('status', self::MENSAJE_SOLICITUD);
        $this->post(route('password.email'), ['email' => self::CORREO])->assertSessionHas('status', self::MENSAJE_SOLICITUD);

        Mail::assertSentCount(1);
    }

    public function test_si_falla_el_envio_responde_lo_mismo_y_lo_registra_en_el_log(): void
    {
        $this->crearUsuario();

        // Transporte que simula un servidor de correo caído
        Mail::extend('fallido', fn () => new class () extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection could not be established with host "127.0.0.1:1025".');
            }

            public function __toString(): string
            {
                return 'fallido://';
            }
        });
        config([
            'mail.mailers.fallido' => ['transport' => 'fallido'],
            'mail.default' => 'fallido',
        ]);
        Log::shouldReceive('error')->once();

        $this->post(route('password.email'), ['email' => self::CORREO])
            ->assertRedirect()
            ->assertSessionHas('status', self::MENSAJE_SOLICITUD);
    }

    public function test_un_enlace_usado_no_se_puede_reutilizar(): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario);

        $this->restablecer($token, self::CLAVE_NUEVA)->assertRedirect(route('login'));

        $this->get($this->enlace($token))
            ->assertStatus(410)
            ->assertSee('Solicitar un enlace nuevo');
        $this->restablecer($token, 'Otra-Clave-Distinta-2026')->assertStatus(410);

        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, (string) $usuario->fresh()?->password));
    }

    // El enlace se arma con APP_URL: una cabecera Host falsa no puede desviar el token a otro dominio
    public function test_el_enlace_usa_app_url_aunque_la_peticion_traiga_otro_host(): void
    {
        Mail::fake();
        config(['app.url' => 'http://sgld.test']);
        $this->crearUsuario();

        $this->post('http://atacante.test/forgot-password', ['email' => self::CORREO])
            ->assertSessionHas('status', self::MENSAJE_SOLICITUD);

        $enlace = (string) $this->correoEnviado()->enlaceUrl;

        $this->assertStringStartsWith('http://sgld.test/reset-password/', $enlace);
        $this->assertStringNotContainsString('atacante', $enlace);
    }

    public function test_el_sexto_envio_en_un_minuto_responde_429_en_espanol(): void
    {
        Mail::fake();

        for ($intento = 1; $intento <= 5; $intento++) {
            $this->post(route('password.email'), ['email' => "intento{$intento}@liga.test"])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'intento6@liga.test'])
            ->assertStatus(429)
            ->assertSee('Demasiados intentos')
            ->assertSee('Espera un minuto y vuelve a intentarlo.');

        // Cada ruta tiene su propio contador: restablecer no queda bloqueado por las solicitudes
        $this->post(route('password.update'), [])->assertStatus(302);
    }

    // CA2: un enlace vencido se rechaza y se ofrece solicitar uno nuevo
    public function test_un_enlace_vencido_muestra_la_opcion_de_solicitar_uno_nuevo(): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario);

        $this->travel(61)->minutes();

        $this->get($this->enlace($token))
            ->assertStatus(410)
            ->assertSee('El enlace no es válido o ya venció')
            ->assertSee('Solicitar un enlace nuevo')
            ->assertSee(route('password.request'));
    }

    public function test_con_un_enlace_vencido_no_cambia_la_contrasena(): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario);

        $this->travel(61)->minutes();

        $this->restablecer($token, self::CLAVE_NUEVA)
            ->assertStatus(410)
            ->assertSee('Solicitar un enlace nuevo');

        $this->assertTrue(Hash::check(self::CLAVE_ANTERIOR, (string) $usuario->fresh()?->password));
    }

    public function test_un_token_alterado_se_trata_como_vencido(): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario).'x';

        $this->get($this->enlace($token))->assertStatus(410)->assertSee('Solicitar un enlace nuevo');
        $this->restablecer($token, self::CLAVE_NUEVA)->assertStatus(410);

        // Un correo que no existe recibe la misma pantalla
        $this->get(route('password.reset', ['token' => $token, 'email' => 'nadie@liga.test']))->assertStatus(410);

        $this->assertTrue(Hash::check(self::CLAVE_ANTERIOR, (string) $usuario->fresh()?->password));
    }

    public function test_con_un_enlace_vigente_muestra_el_formulario(): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario);

        $this->get($this->enlace($token))
            ->assertOk()
            ->assertSee('Define tu nueva contraseña')
            ->assertSee('Mínimo 15 caracteres (8 si tienes doble factor); máximo 64.')
            ->assertSee('name="token" value="'.$token.'"', false)
            ->assertSee('autocomplete="new-password"', false);
    }

    // CA3: la nueva contraseña cumple RNF-26
    #[DataProvider('longitudesRechazadas')]
    public function test_rechaza_contrasenas_fuera_de_rnf_26(int $longitud, string $mensaje): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario);

        $this->from($this->enlace($token))
            ->restablecer($token, str_repeat('a', $longitud))
            ->assertRedirect($this->enlace($token))
            ->assertSessionHasErrors(['password' => $mensaje]);

        $this->assertTrue(Hash::check(self::CLAVE_ANTERIOR, (string) $usuario->fresh()?->password));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function longitudesRechazadas(): array
    {
        return [
            '14 caracteres' => [14, 'La contraseña debe tener al menos 15 caracteres.'],
            '65 caracteres' => [65, 'La contraseña no debe exceder los 64 caracteres.'],
        ];
    }

    public function test_acepta_15_caracteres(): void
    {
        $usuario = $this->crearUsuario();

        $this->restablecer(Password::broker()->createToken($usuario), str_repeat('b', 15))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');
    }

    public function test_con_doble_factor_acepta_8_caracteres(): void
    {
        $usuario = $this->crearUsuario();
        $usuario->forceFill(['two_factor_secret' => encrypt('secreto-de-prueba')])->save();

        $this->restablecer(Password::broker()->createToken($usuario), 'Ocho-123')
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Ocho-123', (string) $usuario->fresh()?->password));
    }

    public function test_rechaza_una_confirmacion_distinta(): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario);

        $this->from($this->enlace($token))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => self::CORREO,
                'password' => self::CLAVE_NUEVA,
                'password_confirmation' => 'Otra-Clave-Que-No-Coincide',
            ])
            ->assertSessionHasErrors(['password' => 'Las contraseñas no coinciden.']);
    }

    public function test_una_contrasena_enviada_como_arreglo_no_provoca_un_error_500(): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario);

        $this->from($this->enlace($token))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => self::CORREO,
                'password' => ['x'],
                'password_confirmation' => ['x'],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['password' => 'La nueva contraseña no es válida.']);
    }

    public function test_la_contrasena_anterior_deja_de_servir_y_la_nueva_permite_iniciar_sesion(): void
    {
        $usuario = $this->crearUsuario();

        $this->restablecer(Password::broker()->createToken($usuario), self::CLAVE_NUEVA)->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => self::CORREO, 'password' => self::CLAVE_ANTERIOR]);
        $this->assertGuest();

        $this->post(route('login'), ['email' => self::CORREO, 'password' => self::CLAVE_NUEVA]);
        $this->assertAuthenticatedAs($usuario);
    }

    // CA3: la sesión anterior queda invalidada
    public function test_cierra_las_sesiones_del_titular_y_no_las_de_otro_usuario(): void
    {
        $usuario = $this->crearUsuario();
        $otro = $this->crearUsuario('otro.usuario@liga.test');

        $this->crearSesion('sesion-titular-1', $usuario);
        $this->crearSesion('sesion-titular-2', $usuario);
        $this->crearSesion('sesion-de-otro', $otro);

        $this->restablecer(Password::broker()->createToken($usuario), self::CLAVE_NUEVA)->assertRedirect(route('login'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $usuario->id)->count());
        $this->assertTrue(DB::table('sessions')->where('id', 'sesion-de-otro')->exists());
    }

    public function test_renueva_el_remember_token_quita_la_contrasena_temporal_y_usa_argon2id(): void
    {
        $usuario = $this->crearUsuario(requiereCambio: true);
        $tokenAnterior = $usuario->remember_token;

        $this->restablecer(Password::broker()->createToken($usuario), self::CLAVE_NUEVA)->assertRedirect(route('login'));

        $usuario->refresh();

        $this->assertNotSame($tokenAnterior, $usuario->remember_token);
        $this->assertFalse($usuario->requires_password_change);
        $this->assertSame('argon2id', Hash::info((string) $usuario->password)['algoName']);
    }

    // Auditoría: una sola fila con el motivo y sin secretos
    public function test_registra_una_sola_fila_de_auditoria_sin_la_contrasena(): void
    {
        $usuario = $this->crearUsuario();
        $token = Password::broker()->createToken($usuario);
        $ultimoId = (int) AuditoriaLog::query()->max('id');

        $this->restablecer($token, self::CLAVE_NUEVA)->assertRedirect(route('login'));

        $log = AuditoriaLog::query()->where('id', '>', $ultimoId)->sole();
        $usuario->refresh();

        $this->assertSame('usuarios', $log->tabla_afectada);
        $this->assertSame($usuario->id, $log->registro_id);
        $this->assertSame('actualizar', $log->accion);
        $this->assertSame('Recuperación de contraseña por correo', $log->motivo);
        $this->assertNull($log->usuario_id);

        foreach ([$log->valores_anteriores ?? [], $log->valores_nuevos ?? []] as $valores) {
            $this->assertArrayNotHasKey('password', $valores);
            $this->assertArrayNotHasKey('remember_token', $valores);
        }

        $this->assertStringNotContainsString((string) $usuario->password, (string) json_encode($log->valores_nuevos));
    }

    // Pantallas en español y sin @vite
    public function test_la_pantalla_para_solicitar_el_enlace_abre_en_espanol(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Recuperar contraseña')
            ->assertSee('Enviar enlace')
            ->assertSee('cdn.tailwindcss.com')
            ->assertDontSee('build/assets');

        foreach (['auth/olvide-contrasena', 'auth/restablecer-contrasena', 'auth/enlace-vencido', 'errors/429'] as $vista) {
            $this->assertStringNotContainsString('@vite', (string) file_get_contents(resource_path("views/{$vista}.blade.php")));
        }
    }

    public function test_el_login_muestra_el_enlace_para_recuperar_la_contrasena(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('¿Olvidaste tu contraseña?')
            ->assertSee('href="'.route('password.request').'"', false);
    }

    public function test_la_validacion_del_correo_responde_en_espanol(): void
    {
        $this->post(route('password.email'), ['email' => 'no-es-un-correo'])
            ->assertSessionHasErrors(['email' => 'Escribe un correo electrónico válido.']);
    }

    private function crearUsuario(string $email = self::CORREO, bool $requiereCambio = false): User
    {
        $usuario = User::create([
            'name' => 'Delegado de Prueba',
            'email' => $email,
            'password' => Hash::make(self::CLAVE_ANTERIOR),
            'estatus' => 'activo',
            'rol' => 'Administrador General',
            'requires_password_change' => $requiereCambio,
        ]);
        $usuario->forceFill(['remember_token' => 'token-de-sesion-anterior'])->save();

        return $usuario;
    }

    private function crearSesion(string $id, User $usuario): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $usuario->id,
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Navegador de prueba',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    private function enlace(string $token): string
    {
        return route('password.reset', ['token' => $token, 'email' => self::CORREO]);
    }

    private function restablecer(string $token, string $clave): TestResponse
    {
        return $this->post(route('password.update'), [
            'token' => $token,
            'email' => self::CORREO,
            'password' => $clave,
            'password_confirmation' => $clave,
        ]);
    }

    private function correoEnviado(): CorreoDelSistema
    {
        $enviado = Mail::sent(CorreoDelSistema::class)->first();
        $this->assertInstanceOf(CorreoDelSistema::class, $enviado);

        return $enviado;
    }
}
