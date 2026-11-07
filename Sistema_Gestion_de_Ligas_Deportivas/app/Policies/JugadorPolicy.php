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

    public function transfer(User $user, Jugador $jugador): Response
    {
        return $user->hasRole('Delegado del Club')
            && $user->club_id !== null
            && (int) $user->club_id === (int) $jugador->equipo->club_id
            && $user->can('jugadores.transferir')
                ? Response::allow()
                : Response::deny('Solo el Delegado del club de origen puede transferir a este jugador.');
    }
}
