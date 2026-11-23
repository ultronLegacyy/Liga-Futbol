<?php

namespace App\Mail;

use App\Services\RegistroCorreoService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

// HU-064 (RF-15.1): aviso a un delegado de la publicación o el cambio de un partido; va por la cola (decisión 2)
// y solo después de confirmarse la transacción: si la operación se revierte, el aviso no sale
class AvisoProgramacion extends CorreoDelSistema implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(string $asunto, string $mensaje, string $clave)
    {
        parent::__construct($asunto, $mensaje, tipo: 'aviso_programacion', clave: $clave);
    }

    // Lo llama la cola después del último intento fallido
    public function failed(Throwable $error): void
    {
        app(RegistroCorreoService::class)->marcarFallido($this, $error);
        Log::error('Falló el envío de un aviso de programación.', ['clave' => $this->clave, 'exception' => $error]);
    }
}
