<?php

namespace App\Policies;

use App\Models\Jornada;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Control de acceso para programación, estados, reprogramación e inmutabilidad de partidos (HU-027, HU-029, HU-031, RNF-29)
class PartidoPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            // Regla estricta SCRUM-203 (HU-031 CA1): ningún usuario, ni por petición directa, elimina partidos con historial
            if ($habilidad === 'delete') {
                return null;
            }

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

    public function reprogramar(User $usuario, Partido $partido): Response
    {
        if ($partido->jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus partidos.');
        }

        if ($partido->estado === Partido::ESTADO_FINALIZADO) {
            return Response::deny('Un partido que ya ha finalizado no puede ser reprogramado.');
        }

        return ($usuario->can('partidos.reprogramar') || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para reprogramar partidos.');
    }

    public function cambiarEstado(User $usuario, Partido $partido): Response
    {
        if ($partido->jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus partidos.');
        }

        return ($usuario->can('partidos.reprogramar') || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para actualizar el estado del partido.');
    }

    /**
     * Regla de no eliminación de partidos con historial (HU-031 / RF-06.6 / SCRUM-203).
     */
    public function delete(User $usuario, Partido $partido): Response
    {
        if ($partido->jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus partidos.');
        }

        if (! $partido->esEliminable()) {
            return Response::deny('No es posible eliminar un partido que cuenta con programación activa, cédula arbitral o historial oficial (HU-031).');
        }

        return $usuario->can('partidos.programar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para eliminar partidos.');
    }
}
