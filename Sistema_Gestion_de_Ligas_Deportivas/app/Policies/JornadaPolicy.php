<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Control de acceso para jornadas, descansos y publicación semanal (HU-027, HU-028, HU-030, RNF-29)
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

    /**
     * Enviar programación semanal a revisión (HU-030 / SCRUM-199).
     */
    public function enviarRevision(User $usuario, Jornada $jornada): Response
    {
        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus jornadas.');
        }

        return $usuario->can('partidos.programar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para enviar la programación a revisión.');
    }

    /**
     * Aprobación de la programación semanal por el Comité Ejecutivo (HU-030 / Anexo B / SCRUM-199).
     */
    public function aprobar(User $usuario, Jornada $jornada): Response
    {
        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus jornadas.');
        }

        $esComiteEjecutivo = $usuario->hasAnyRole([
            'Administrador General',
            'Presidente',
            'Secretario',
            'Tesorero',
            'Comisión Arbitral',
            'Comision de la Rama Infantil',
        ]);

        return ($esComiteEjecutivo || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('Solo los integrantes del Comité Ejecutivo o administradores pueden aprobar la programación semanal.');
    }

    /**
     * Publicación oficial de la programación semanal (HU-030 / RF-06.5).
     */
    public function publicar(User $usuario, Jornada $jornada): Response
    {
        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus jornadas.');
        }

        return ($usuario->can('partidos.programar') || $usuario->hasRole('Administrador General'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para publicar la programación semanal.');
    }
}
