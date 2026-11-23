<?php

namespace App\Mail;

use App\Services\RegistroCorreoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

// HU-065 (RF-15.2): recordatorio a un delegado de los adeudos de su club; va por la cola (decisión 2).
// Se encola dentro de la transacción que marca los cargos: si el encolado falla, las marcas se revierten
class RecordatorioPago extends CorreoDelSistema implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(string $asunto, string $mensaje, string $clave)
    {
        parent::__construct($asunto, $mensaje, tipo: 'recordatorio_pago', clave: $clave);
    }

    // Lo llama la cola después del último intento fallido
    public function failed(Throwable $error): void
    {
        app(RegistroCorreoService::class)->marcarFallido($this, $error);
        Log::error('Falló el envío de un recordatorio de pago.', ['clave' => $this->clave, 'exception' => $error]);
    }
}
