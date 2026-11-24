<?php

namespace App\Listeners;

use App\Events\ResultadoValidado;
use App\Services\TablaPosicionesService;

/**
 * HU-040 CA2 y HU-041 CA1: al validar un resultado se recalcula la tabla de su grupo.
 * Corre en la misma transacción que la validación: si el cálculo falla, el resultado no queda validado.
 */
class RecalcularTablaPosiciones
{
    public function __construct(private readonly TablaPosicionesService $tablas)
    {
    }

    public function handle(ResultadoValidado $evento): void
    {
        $grupo = $evento->cedula->partido?->jornada?->grupo;

        if ($grupo !== null) {
            $this->tablas->recalcular($grupo);
        }
    }
}
