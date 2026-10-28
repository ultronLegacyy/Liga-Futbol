<?php

namespace App\Console\Commands;

use App\Mail\CorreoDelSistema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ProbarCorreo extends Command
{
    protected $signature = 'sgld:probar-correo
        {destinatario : Correo al que se envía la prueba}';

    protected $description = 'Envía un correo de prueba con la configuración de correo del entorno actual (IC-03, RNF-42)';

    public function handle(): int
    {
        $destinatario = (string) $this->argument('destinatario');

        if (Validator::make(['destinatario' => $destinatario], ['destinatario' => 'required|email'])->fails()) {
            $this->error("«{$destinatario}» no es un correo válido.");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $configuracion = config("mail.mailers.{$mailer}");
        $configuracion = is_array($configuracion) ? $configuracion : [];

        // Solo se indica si hay usuario; la contraseña nunca se muestra
        $this->info('Configuración de correo de este entorno:');
        $this->line("  - Mailer: {$mailer}");
        $this->line('  - Host: '.(string) ($configuracion['host'] ?? 'no aplica'));
        $this->line('  - Puerto: '.(string) ($configuracion['port'] ?? 'no aplica'));
        $this->line('  - Usuario: '.(filled($configuracion['username'] ?? null) ? 'configurado' : 'sin usuario'));

        try {
            Mail::to($destinatario)->send(new CorreoDelSistema(
                'Correo de prueba del SGLD',
                "Si recibiste este mensaje, el envío de correo del Sistema de Gestión de Ligas Deportivas funciona en este entorno.\n\nEntorno: ".app()->environment(),
            ));
        } catch (TransportExceptionInterface $e) {
            // El detalle técnico queda en el log; en la consola, solo un mensaje sin la traza
            Log::error('Falló el envío del correo de prueba (sgld:probar-correo).', [
                'mailer' => $mailer,
                'destinatario' => $destinatario,
                'exception' => $e,
            ]);

            $this->error('No se pudo enviar el correo de prueba: el servidor de correo rechazó la conexión o la autenticación.');
            $this->line('Revisa MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME y MAIL_PASSWORD en el .env y ejecuta php artisan config:clear. El detalle quedó en el log.');

            return self::FAILURE;
        }

        $this->info("Correo de prueba enviado a {$destinatario}.");

        return self::SUCCESS;
    }
}
