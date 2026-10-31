<?php

namespace Tests\Feature;

use App\Mail\CorreoDelSistema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Tests\TestCase;

class CorreoSmtpTest extends TestCase
{
    // CA2: el correo base tiene versión HTML y versión de texto plano
    public function test_el_correo_del_sistema_se_genera_en_html_y_en_texto(): void
    {
        $correo = new CorreoDelSistema('Aviso de prueba', 'Partido reprogramado: Gómez & Cía');

        $correo->assertHasSubject('Aviso de prueba');
        $correo->assertSeeInHtml('<html lang="es">', false);
        $correo->assertSeeInHtml('Partido reprogramado: Gómez & Cía');
        $correo->assertSeeInText('Aviso de prueba');
        $correo->assertSeeInText('Partido reprogramado: Gómez & Cía');
    }

    public function test_la_version_de_texto_no_tiene_etiquetas_html(): void
    {
        $correo = new CorreoDelSistema('Aviso de prueba', "Primera línea\nSegunda línea");

        $correo->assertSeeInText("Primera línea\nSegunda línea");
        $correo->assertDontSeeInText('<');
        $correo->assertDontSeeInText('>');
    }

    // CA1 y CA4: el comando envía con la configuración del entorno actual
    public function test_el_comando_envia_un_correo_de_prueba_al_destinatario(): void
    {
        Mail::fake();
        // phpunit.xml usa el mailer array, que no envía; aquí se simula el entorno real
        config(['mail.default' => 'smtp']);

        $this->artisan('sgld:probar-correo', ['destinatario' => 'prueba@example.com'])
            ->expectsOutputToContain('Correo de prueba enviado a prueba@example.com.')
            ->assertSuccessful();

        Mail::assertSentCount(1);
        Mail::assertSent(CorreoDelSistema::class, fn (CorreoDelSistema $correo) => $correo->hasTo('prueba@example.com'));
    }

    public function test_el_comando_rechaza_un_destinatario_invalido_sin_enviar_nada(): void
    {
        Mail::fake();

        $this->artisan('sgld:probar-correo', ['destinatario' => 'no-es-un-correo'])
            ->expectsOutputToContain('«no-es-un-correo» no es un correo válido.')
            ->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_el_comando_no_muestra_la_contrasena_del_correo(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.username' => 'usuario-prueba',
            'mail.mailers.smtp.password' => 'Clave-Secreta-123',
        ]);

        $codigo = Artisan::call('sgld:probar-correo', ['destinatario' => 'prueba@example.com']);
        $salida = Artisan::output();

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('Usuario: configurado', $salida);
        $this->assertStringNotContainsString('Clave-Secreta-123', $salida);
    }

    public function test_si_el_transporte_falla_termina_con_un_mensaje_en_espanol_sin_la_traza(): void
    {
        // Transporte que simula un servidor que rechaza la autenticación
        Mail::extend('fallido', fn () => new class () extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Expected response code "235" but got code "535", with message "535 Authentication failed".');
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

        $codigo = Artisan::call('sgld:probar-correo', ['destinatario' => 'prueba@example.com']);
        $salida = Artisan::output();

        $this->assertSame(1, $codigo, $salida);
        $this->assertStringContainsString('No se pudo enviar el correo de prueba', $salida);
        $this->assertStringNotContainsString('535', $salida);
        $this->assertStringNotContainsString('#0 ', $salida);
    }

    /**
     * Errores de configuración: el comando termina con un mensaje en español, sin mostrar la excepción.
     *
     * @param  array<string, mixed>  $configuracion
     */
    #[DataProvider('configuracionesInvalidas')]
    public function test_si_la_configuracion_es_invalida_termina_con_un_mensaje_en_espanol(array $configuracion, string $textoDeLaExcepcion): void
    {
        config($configuracion);
        Log::shouldReceive('error')->once();

        $codigo = Artisan::call('sgld:probar-correo', ['destinatario' => 'prueba@example.com']);
        $salida = Artisan::output();

        $this->assertSame(1, $codigo, $salida);
        $this->assertStringContainsString('No se pudo enviar el correo de prueba: revisa la configuración de correo', $salida);
        $this->assertStringNotContainsString($textoDeLaExcepcion, $salida);
        $this->assertStringNotContainsString('Correo de prueba enviado', $salida);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function configuracionesInvalidas(): array
    {
        return [
            'MAIL_SCHEME=tls' => [
                ['mail.default' => 'smtp', 'mail.mailers.smtp.url' => null, 'mail.mailers.smtp.scheme' => 'tls'],
                'is not supported',
            ],
            'MAIL_FROM_ADDRESS inválido' => [
                ['mail.default' => 'array', 'mail.from.address' => 'no-es-correo'],
                'does not comply',
            ],
            'MAIL_FROM_ADDRESS vacío' => [
                ['mail.default' => 'array', 'mail.from.address' => ''],
                'does not comply',
            ],
            'MAIL_MAILER inexistente' => [
                ['mail.default' => 'no-existe'],
                'is not defined',
            ],
        ];
    }

    // Con log o array el correo no sale a ningún servidor: no se puede decir «enviado»
    public function test_con_el_mailer_log_avisa_que_el_correo_no_salio(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);

        $codigo = Artisan::call('sgld:probar-correo', ['destinatario' => 'prueba@example.com']);
        $salida = Artisan::output();

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('El correo no salió a ningún servidor: el mailer «log» no envía correos.', $salida);
        $this->assertStringNotContainsString('enviado', $salida);
    }

    /**
     * CA3 y CA4: el mailer smtp toma servidor y credenciales de la configuración (variables de entorno).
     *
     * @param  array{host: string, port: int, scheme: ?string, username: string, password: string, require_tls: bool}  $entorno
     */
    #[DataProvider('entornos')]
    public function test_el_mailer_smtp_toma_servidor_y_credenciales_de_la_configuracion(array $entorno, bool $tls): void
    {
        config(['mail.mailers.smtp' => array_merge(config('mail.mailers.smtp'), $entorno, ['url' => null])]);

        $transporte = Mail::mailer('smtp')->getSymfonyTransport();

        $this->assertInstanceOf(EsmtpTransport::class, $transporte);
        $this->assertSame($entorno['username'], $transporte->getUsername());
        $this->assertSame($entorno['password'], $transporte->getPassword());
        $this->assertSame($entorno['require_tls'], $transporte->isTlsRequired());

        $conexion = $transporte->getStream();

        $this->assertInstanceOf(SocketStream::class, $conexion);
        $this->assertSame($entorno['host'], $conexion->getHost());
        $this->assertSame($entorno['port'], $conexion->getPort());
        $this->assertSame($tls, $conexion->isTLS());
    }

    /**
     * @return array<string, array{array{host: string, port: int, scheme: ?string, username: string, password: string, require_tls: bool}, bool}>
     */
    public static function entornos(): array
    {
        return [
            'desarrollo con Mailpit' => [
                ['host' => '127.0.0.1', 'port' => 1025, 'scheme' => null, 'username' => 'sgld', 'password' => 'mailpit-local', 'require_tls' => false],
                false,
            ],
            'producción con SMTPS' => [
                ['host' => 'smtp.proveedor.test', 'port' => 465, 'scheme' => 'smtps', 'username' => 'notificaciones', 'password' => 'clave-de-prueba', 'require_tls' => true],
                true,
            ],
        ];
    }
}
