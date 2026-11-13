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
            // Reglas de inmutabilidad y estados terminales (SCRUM-44, SCRUM-46):
            // Ningún usuario, ni por petición directa, elimina partidos con historial ni reprograma/altera partidos finalizados o cancelados
            if (in_array($habilidad, ['delete', 'update', 'reprogramar', 'cambiarEstado'], true)) {
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

        if (in_array($partido->estado, [Partido::ESTADO_FINALIZADO, Partido::ESTADO_CANCELADO], true)) {
            return Response::deny('Un partido finalizado o cancelado no puede ser modificado.');
        }

        return ($usuario->hasRole('Administrador General') || $usuario->can('partidos.reprogramar') || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para modificar o reprogramar partidos.');
    }

    public function reprogramar(User $usuario, Partido $partido): Response
    {
        if ($partido->jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus partidos.');
        }

        if (in_array($partido->estado, [Partido::ESTADO_FINALIZADO, Partido::ESTADO_CANCELADO], true)) {
            return Response::deny('Un partido finalizado o cancelado no puede ser reprogramado.');
        }

        if (in_array($partido->estado, [Partido::ESTADO_BORRADOR, Partido::ESTADO_EN_JUEGO], true)) {
            return Response::deny('Un partido en borrador o en juego no puede ser reprogramado.');
        }

        return ($usuario->hasRole('Administrador General') || $usuario->can('partidos.reprogramar') || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para reprogramar partidos.');
    }

    public function cambiarEstado(User $usuario, Partido $partido): Response
    {
        if ($partido->jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus partidos.');
        }

        if (in_array($partido->estado, [Partido::ESTADO_FINALIZADO, Partido::ESTADO_CANCELADO], true)) {
            return Response::deny('Un partido finalizado o cancelado no admite cambios de estado.');
        }

        return ($usuario->hasRole('Administrador General') || $usuario->can('partidos.reprogramar') || $usuario->can('partidos.programar'))
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

        // HU-036: Si el partido tiene cédula con datos capturados, delegar a la validación
        // de negocio en ProgramacionJornadaService para responder 422 en JSON y mensaje de sesión en web
        if ($partido->cedula()->conDatosCapturados()->exists()) {
            return $usuario->can('partidos.programar')
                ? Response::allow()
                : Response::deny('No cuenta con privilegios para eliminar partidos.');
        }

        if (! $partido->esEliminable()) {
            return Response::deny('No es posible eliminar un partido que cuenta con programación activa, cédula arbitral o historial oficial (HU-031).');
        }

        return $usuario->can('partidos.programar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para eliminar partidos.');
    }
}
