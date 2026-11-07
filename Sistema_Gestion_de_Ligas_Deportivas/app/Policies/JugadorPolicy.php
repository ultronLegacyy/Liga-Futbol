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

    public function deactivate(User $user, Jugador $jugador): Response
    {
        return $this->puedeGestionarEquipo($user, $jugador)
            ? Response::allow()
            : Response::deny('No cuenta con autorización para dar de baja a este jugador.');
    }

    public function renew(User $user, Jugador $jugador): Response
    {
        return $this->puedeGestionarEquipo($user, $jugador)
            ? Response::allow()
            : Response::deny('No cuenta con autorización para renovar a este jugador.');
    }

    private function puedeGestionarEquipo(User $user, Jugador $jugador): bool
    {
        return $user->hasRole('Delegado del Club')
            && $user->club_id !== null
            && (int) $user->club_id === (int) $jugador->equipo->club_id
            && $user->can('jugadores.crear');
    }
}
