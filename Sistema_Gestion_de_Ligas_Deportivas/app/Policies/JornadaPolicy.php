<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Control de acceso para jornadas y descansos (HU-027, HU-028, RNF-29)
class JornadaPolicy
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
            : Response::deny('No cuenta con privilegios para consultar jornadas.');
    }

    public function view(User $usuario, Jornada $jornada): Response
    {
        return $usuario->can('partidos.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar esta jornada.');
    }

    public function create(User $usuario, Grupo $grupo): Response
    {
        if ($grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus jornadas.');
        }

        return $usuario->can('partidos.programar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para crear jornadas.');
    }

    public function update(User $usuario, Jornada $jornada): Response
    {
        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus jornadas.');
        }

        return $usuario->can('partidos.programar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para modificar jornadas.');
    }

    public function delete(User $usuario, Jornada $jornada): Response
    {
        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus jornadas.');
        }

        return $usuario->can('partidos.programar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para eliminar jornadas.');
    }
}
