<?php

namespace App\Listeners;

use App\Events\ResultadoValidado;
use App\Services\EstadisticasGrupoService;

/**
 * HU-043 CA2: al validar el último resultado de una jornada se guarda la copia histórica de la tabla.
 * Corre en la transacción de la validación (HU-040): si falla, no queda nada a medias.
 */
class ConservarTablaDeJornada
{
    public function __construct(private readonly EstadisticasGrupoService $estadisticas)
    {
    }

    public function handle(ResultadoValidado $evento): void
    {
        $grupo = $evento->cedula->partido?->jornada?->grupo;

        if ($grupo !== null) {
            $this->estadisticas->conservarHistoricos($grupo);
        }
    }
}
