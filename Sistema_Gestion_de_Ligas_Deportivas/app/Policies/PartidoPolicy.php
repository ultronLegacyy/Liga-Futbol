<?php

namespace App\Policies;

use App\Models\Jornada;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Control de acceso para programación de partidos (HU-027, RNF-29)
class PartidoPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $usuario): Response
    {
        return $usuario->can('partidos.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar partidos.');
    }

    public function view(User $usuario, Partido $partido): Response
    {
        return $usuario->can('partidos.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar este partido.');
    }

    public function create(User $usuario, Jornada $jornada): Response
    {
        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus partidos.');
        }

        return $usuario->can('partidos.programar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para programar partidos.');
    }

    public function update(User $usuario, Partido $partido): Response
    {
        if ($partido->jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus partidos.');
        }

        return ($usuario->can('partidos.reprogramar') || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para modificar o reprogramar partidos.');
    }

    public function delete(User $usuario, Partido $partido): Response
    {
        if ($partido->jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus partidos.');
        }

        return $usuario->can('partidos.programar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para eliminar partidos.');
    }
}
