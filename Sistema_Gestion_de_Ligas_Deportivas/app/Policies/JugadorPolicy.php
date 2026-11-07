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

        return $esElJugador || $esDirectivoDelClub
            ? Response::allow()
            : Response::deny('No cuenta con autorización para consultar los datos de este jugador.');
    }

    public function unlink(User $user, Jugador $jugador): Response
    {
        return $user->hasRole('Administrador General')
            ? Response::allow()
            : Response::deny('Solo el Administrador General puede desvincular una cuenta de jugador.');
    }
}
