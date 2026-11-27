<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * HU-055 y HU-056: generar las llaves de liguilla y crear sus rondas. Lo hace quien puede modificar la temporada
 * (Presidente y Administrador General, D-043 y decisión 4 de Daniel), nunca en una temporada cerrada; el mensaje habla
 * de la liguilla, no de temporadas.
 */
class LlaveLiguillaPolicy
{
    public function create(User $usuario, Grupo $grupo): Response
    {
        return $this->operar($usuario, $grupo, 'ya no se generan llaves de liguilla', 'Solo el Presidente o el Administrador General pueden generar las llaves de liguilla.');
    }

    public function crearRonda(User $usuario, Grupo $grupo): Response
    {
        return $this->operar($usuario, $grupo, 'ya no se crean rondas de liguilla', 'Solo el Presidente o el Administrador General pueden crear las rondas de la liguilla.');
    }

    private function operar(User $usuario, Grupo $grupo, string $cerrada, string $mensaje): Response
    {
        $temporada = $grupo->temporada;

        if ($temporada === null || $temporada->estado === 'cerrada') {
            return Response::deny("La temporada de este grupo está cerrada: {$cerrada}.");
        }

        return $usuario->can('update', $temporada) ? Response::allow() : Response::deny($mensaje);
    }
}
