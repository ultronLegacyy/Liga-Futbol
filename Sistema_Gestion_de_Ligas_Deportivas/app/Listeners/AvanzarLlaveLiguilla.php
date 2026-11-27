<?php

namespace App\Listeners;

use App\Events\ResultadoValidado;
use App\Services\AvanceLiguillaService;

/**
 * HU-056 CA1: al validar el resultado de un partido de liguilla, el ganador avanza y el otro queda eliminado.
 * Corre en la transacción de la validación (HU-040): si falla, el resultado tampoco queda validado.
 */
class AvanzarLlaveLiguilla
{
    public function __construct(private readonly AvanceLiguillaService $avances)
    {
    }

    public function handle(ResultadoValidado $evento): void
    {
        $this->avances->resolverLlave($evento->cedula);
    }
}
