<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvisoCuentaBloqueada extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Crear una nueva instancia de la notificación de cuenta bloqueada.
     */
    public function __construct(
        public string $email,
        public string $ip,
        public string $fechaHora,
        public int $duracionMinutos = 15,
        public ?string $nombre = null,
    ) {
    }

    /**
     * Obtener el sobre del mensaje con el asunto formal en español.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aviso de seguridad: Cuenta bloqueada temporalmente por intentos fallidos'
        );
    }

    /**
     * Obtener la definición del contenido con vistas duales HTML y texto plano.
     */
    public function content(): Content
    {
        $saludo = $this->nombre ? "Hola {$this->nombre}," : 'Estimado(a) usuario,';
        $mensaje = "{$saludo}\n\n"
            ."Le informamos que su cuenta ({$this->email}) ha sido bloqueada temporalmente por un período de {$this->duracionMinutos} minutos.\n\n"
            ."Motivo: Se registraron 5 intentos fallidos consecutivos de inicio de sesión.\n"
            ."Fecha y hora del evento: {$this->fechaHora}\n"
            ."Dirección IP de origen: {$this->ip}\n\n"
            ."Durante este período de {$this->duracionMinutos} minutos no será posible iniciar sesión en la plataforma, incluso proporcionando las credenciales correctas.\n"
            ."Una vez transcurrido este tiempo, la cuenta se desbloqueará de forma automática.\n\n"
            .'Si usted no intentó iniciar sesión recientemente, le recomendamos cambiar su contraseña de inmediato una vez finalizado el bloqueo y comunicarse con el administrador del sistema.';

        return new Content(
            view: 'correos.sistema',
            text: 'correos.sistema-texto',
            with: [
                'asunto' => 'Aviso de seguridad: Cuenta bloqueada temporalmente por intentos fallidos',
                'mensaje' => $mensaje,
            ],
        );
    }
}
