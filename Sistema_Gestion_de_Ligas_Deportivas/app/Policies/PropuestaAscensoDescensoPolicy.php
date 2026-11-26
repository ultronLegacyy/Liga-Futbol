<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * HU-057: generar propuestas de ascenso y descenso. Mismo rol que la liguilla (HU-055, D-043): quien puede modificar
 * la temporada (Presidente y Administrador General), nunca en una temporada cerrada. El rol queda por confirmar.
 */
class PropuestaAscensoDescensoPolicy
{
    public function create(User $usuario, Grupo $grupo): Response
    {
        $temporada = $grupo->temporada;

        if ($temporada === null || $temporada->estado === 'cerrada') {
            return Response::deny('La temporada de este grupo está cerrada: ya no se generan propuestas de ascenso y descenso.');
        }

        return $usuario->can('update', $temporada)
            ? Response::allow()
            : Response::deny('Solo el Presidente o el Administrador General pueden generar propuestas de ascenso y descenso.');
    }
}
