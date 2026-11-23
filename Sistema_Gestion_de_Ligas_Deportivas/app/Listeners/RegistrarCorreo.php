<?php

namespace App\Listeners;

use App\Services\RegistroCorreoService;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

// HU-067: punto único del registro de correos; Laravel lo descubre solo, como AuditarCambiosDePermisos
class RegistrarCorreo
{
    public function __construct(private readonly RegistroCorreoService $registro)
    {
    }

    // Devolver false cancela el envío (aviso ya enviado); null deja seguir a los demás oyentes
    public function handleEnviando(MessageSending $evento): ?bool
    {
        return $this->registro->alEnviar($evento->message) ? null : false;
    }

    public function handleEnviado(MessageSent $evento): void
    {
        $this->registro->alConfirmar($evento->message);
    }
}
