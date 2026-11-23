<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

// Correo base del sistema; lo reutilizan la recuperación de contraseña y los comunicados
class CorreoDelSistema extends Mailable
{
    // HU-067: identifica el envío en el registro de correos; un aviso con la misma clave no se manda dos veces
    public string $clave;

    public function __construct(
        public string $asunto,
        public string $mensaje,
        // Botón opcional, por ejemplo el enlace de recuperación de contraseña (HU-003)
        public ?string $enlaceUrl = null,
        public ?string $enlaceTexto = null,
        // HU-067: tipo de correo para el registro (RegistroCorreo::TIPOS)
        public string $tipo = 'otro',
        ?string $clave = null,
    ) {
        $this->clave = $clave ?? (string) Str::uuid();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-SGLD-Tipo' => $this->tipo, 'X-SGLD-Clave' => $this->clave]);
    }

    // Cada correo lleva versión HTML y versión de texto plano (Tabla 7 de la ERS)
    public function content(): Content
    {
        return new Content(
            view: 'correos.sistema',
            text: 'correos.sistema-texto',
        );
    }
}
