<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Control de acceso para jornadas, descansos y publicación semanal (HU-027, HU-028, HU-030, RNF-29)
class JornadaPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            // El Administrador General no puede omitir el orden de las etapas del flujo formal (HU-030 / SCRUM-199)
            if (in_array($habilidad, ['enviarRevision', 'aprobar', 'publicar'], true)) {
                return null;
            }

            return true;
        }

        return null;
    }

    public function viewAny(User $usuario): Response
    {
        return ($usuario->can('partidos.ver') || $usuario->can('programacion.aprobar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar jornadas.');
    }

    public function view(User $usuario, Jornada $jornada): Response
    {
        return ($usuario->can('partidos.ver') || $usuario->can('programacion.aprobar'))
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

        // HU-036: Si la jornada tiene partidos con cédula capturada, delegar a la validación
        // de negocio en ProgramacionJornadaService para lanzar ValidationException con mensaje de sesión en jornada_id
        if ($jornada->partidos()->conCedulaCapturada()->exists()) {
            return $usuario->can('partidos.programar')
                ? Response::allow()
                : Response::deny('No cuenta con privilegios para eliminar jornadas.');
        }

        // Obligatorio 2: Solo jornadas en borrador sin partidos protegidos pueden eliminarse (HU-030, HU-031)
        if (! $jornada->esBorrador()) {
            return Response::deny("No se puede eliminar una jornada en estado '{$jornada->estado}'. Solo jornadas en estado borrador pueden eliminarse.");
        }

        $tieneNoEliminables = $jornada->partidos()->get()->contains(fn (Partido $p) => ! $p->esEliminable());
        if ($tieneNoEliminables) {
            return Response::deny('No se puede eliminar la jornada porque contiene partidos protegidos (con cédula arbitral, historial de reprogramación o fuera de borrador).');
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

        if ($jornada->estado !== Jornada::ESTADO_BORRADOR) {
            return Response::deny("Solo una jornada en estado borrador puede enviarse a revisión (estado actual: {$jornada->estado}).");
        }

        return ($usuario->hasRole('Administrador General') || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para enviar la programación a revisión.');
    }

    /**
     * Aprobación de la programación semanal (HU-030 / Anexo B / SCRUM-199).
     * Obligatorio 9: Se desacopla de partidos.programar para evitar autoaprobación del Programador.
     */
    public function aprobar(User $usuario, Jornada $jornada): Response
    {
        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus jornadas.');
        }

        if ($jornada->estado !== Jornada::ESTADO_EN_REVISION) {
            return Response::deny("Solo una jornada en estado de revisión puede ser aprobada (estado actual: {$jornada->estado}).");
        }

        return ($usuario->hasRole('Administrador General') || $usuario->can('programacion.aprobar'))
            ? Response::allow()
            : Response::deny('Solo los usuarios autorizados para aprobar programación o administradores generales pueden aprobar la jornada.');
    }

    /**
     * Publicación oficial de la programación semanal (HU-030 / RF-06.5).
     */
    public function publicar(User $usuario, Jornada $jornada): Response
    {
        if ($jornada->grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus jornadas.');
        }

        if ($jornada->estado !== Jornada::ESTADO_APROBADA) {
            return Response::deny("Solo una jornada previamente aprobada puede ser publicada (estado actual: {$jornada->estado}).");
        }

        return ($usuario->can('partidos.programar') || $usuario->hasRole('Administrador General'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para publicar la programación semanal.');
    }
}
