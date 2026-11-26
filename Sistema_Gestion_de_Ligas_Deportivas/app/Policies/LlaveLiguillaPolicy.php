<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * HU-055: generar las llaves de liguilla de un grupo. Lo hace quien puede modificar la temporada (Presidente y
 * Administrador General, D-043), nunca en una temporada cerrada; el mensaje habla de la liguilla, no de temporadas.
 */
class LlaveLiguillaPolicy
{
    public function create(User $usuario, Grupo $grupo): Response
    {
        $temporada = $grupo->temporada;

        if ($temporada === null || $temporada->estado === 'cerrada') {
            return Response::deny('La temporada de este grupo está cerrada: ya no se generan llaves de liguilla.');
        }

        return $usuario->can('update', $temporada)
            ? Response::allow()
            : Response::deny('Solo el Presidente o el Administrador General pueden generar las llaves de liguilla.');
    }
}
