<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// Correo base del sistema; lo reutilizan la recuperación de contraseña y los comunicados
class CorreoDelSistema extends Mailable
{
    public function __construct(
        public string $asunto,
        public string $mensaje,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
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
