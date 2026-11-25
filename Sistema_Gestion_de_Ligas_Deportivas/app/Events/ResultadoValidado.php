<?php

namespace App\Events;

use App\Models\CedulaArbitral;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * HU-040 CA2: un resultado pasó a oficial. Se emite dentro de la misma transacción que la validación,
 * así que lo que haga un oyente (el cálculo de la tabla de HU-041) se confirma o se deshace junto con ella.
 */
class ResultadoValidado
{
    use Dispatchable;

    public function __construct(public readonly CedulaArbitral $cedula)
    {
    }
}
