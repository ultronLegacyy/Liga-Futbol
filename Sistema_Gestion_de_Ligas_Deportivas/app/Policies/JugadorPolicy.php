<?php

namespace App\Policies;

use App\Models\Jugador;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JugadorPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Administrador General') ? true : null;
    }

    public function link(User $user): bool
    {
        return $user->rol === 'Jugador' || $user->hasRole('Jugador');
    }

    public function view(User $user, Jugador $jugador): Response
    {
        $esElJugador = $user->persona_id !== null
            && (int) $user->persona_id === (int) $jugador->persona_id;
        $equipo = $jugador->equipo;
        $esDirectivoDelClub = $equipo !== null
            && $user->club_id !== null
            && (int) $user->club_id === (int) $equipo->club_id
            && $user->hasAnyRole(['Presidente del Club', 'Delegado del Club']);

        return $esElJugador || $esDirectivoDelClub || $this->esSecretario($user)
            ? Response::allow()
            : Response::deny('No cuenta con autorización para consultar los datos de este jugador.');
    }

    public function update(User $user, Jugador $jugador): Response
    {
        $esElJugador = $user->persona_id !== null
            && (int) $user->persona_id === (int) $jugador->persona_id;
        $esDirectivoDelClub = $user->club_id !== null
            && (int) $user->club_id === (int) $jugador->equipo->club_id
            && $user->hasAnyRole(['Presidente del Club', 'Delegado del Club']);

        return $esElJugador || $esDirectivoDelClub
            ? Response::allow()
            : Response::deny('No cuenta con autorización para editar los datos de este jugador.');
    }

    public function unlink(User $user, Jugador $jugador): Response
    {
        return $user->hasRole('Administrador General')
            ? Response::allow()
            : Response::deny('Solo el Administrador General puede desvincular una cuenta de jugador.');
    }

    /**
     * Listado de jugadores (HU-017): el Delegado y el Presidente del Club ven los de su club;
     * el Secretario (HU-018) y el Administrador General (before) ven todos.
     */
    public function viewAny(User $user): Response
    {
        $esDirectivoDeClub = $user->club_id !== null && $user->hasAnyRole(['Presidente del Club', 'Delegado del Club']);

        return $esDirectivoDeClub || $this->esSecretario($user)
            ? Response::allow()
            : Response::deny('No cuenta con autorización para consultar el listado de jugadores.');
    }

    /** HU-018: cambia el estado el Secretario con su permiso, o el Administrador General (before). */
    public function changeStatus(User $user, Jugador $jugador): Response
    {
        return $user->hasRole('Secretario') && $user->can('jugadores.estado.cambiar')
            ? Response::allow()
            : Response::deny('Solo el Secretario puede cambiar el estado de un jugador.');
    }

    /**
     * El Secretario (Consejo Directivo) supervisa el padrón y la documentación de la Liga
     * (RF-04): consulta a los jugadores de todos los clubes.
     */
    private function esSecretario(User $user): bool
    {
        return $user->hasRole('Secretario') && $user->can('jugadores.ver');
    }

    /** HU-017, criterio 1: el Delegado del club de la inscripción o el Administrador General (before). */
    public function deactivate(User $user, Jugador $jugador): Response
    {
        return $this->esDelegadoDelClub($user, $jugador)
            ? Response::allow()
            : Response::deny('Solo el Delegado del club del jugador puede darlo de baja.');
    }

    /** HU-017, criterio 2: el Delegado del club de la inscripción o el Administrador General (before). */
    public function renew(User $user, Jugador $jugador): Response
    {
        return $this->esDelegadoDelClub($user, $jugador)
            ? Response::allow()
            : Response::deny('Solo el Delegado del club del jugador puede renovarlo.');
    }

    /**
     * HU-017, criterio 3: el Delegado del club de origen con el permiso jugadores.transferir,
     * o el Administrador General (before). Quién inicia la transferencia está pendiente del PO.
     */
    public function transfer(User $user, Jugador $jugador): Response
    {
        return $this->esDelegadoDelClub($user, $jugador) && $user->can('jugadores.transferir')
            ? Response::allow()
            : Response::deny('Solo el Delegado del club de origen puede transferir al jugador.');
    }

    private function esDelegadoDelClub(User $user, Jugador $jugador): bool
    {
        return $user->club_id !== null
            && (int) $user->club_id === (int) $jugador->equipo->club_id
            && $user->hasRole('Delegado del Club');
    }
}
