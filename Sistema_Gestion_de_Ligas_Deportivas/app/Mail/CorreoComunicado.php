<?php

namespace App\Mail;

use App\Services\RegistroCorreoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

// HU-066 (RF-15.3): un comunicado para una persona del grupo; cada quien recibe el suyo, sin ver a los demás.
// Se encola dentro de la transacción que guarda el comunicado: si la cola falla, no queda un envío a medias
class CorreoComunicado extends CorreoDelSistema implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(string $asunto, string $mensaje, string $clave)
    {
        parent::__construct($asunto, $mensaje, tipo: 'comunicado', clave: $clave);
    }

    // Lo llama la cola después del último intento fallido
    public function failed(Throwable $error): void
    {
        app(RegistroCorreoService::class)->marcarFallido($this, $error);
        Log::error('Falló el envío de un comunicado.', ['clave' => $this->clave, 'exception' => $error]);
    }
}
