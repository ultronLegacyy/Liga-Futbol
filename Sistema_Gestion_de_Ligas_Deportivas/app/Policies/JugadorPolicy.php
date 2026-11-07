<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class JugadorPolicy
{
    public function link(User $usuario): bool
    {
        return $usuario->rol === 'Jugador' || $usuario->hasRole('Jugador');
    }

    public function unlink(User $administrador, User $cuentaJugador): Response
    {
        return $administrador->hasRole('Administrador General')
            ? Response::allow()
            : Response::deny('Solo el Administrador General puede desvincular una cuenta de jugador.');
    }
}
