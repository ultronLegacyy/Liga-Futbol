<?php

namespace App\Policies;

use App\Models\TarjetonDigital;
use App\Models\Club;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TarjetonDigitalPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Administrador General') ? true : null;
    }

    public function viewAny(User $user, Club $club, Temporada $temporada): Response
    {
        return $this->presidentePuedeConsultar($user, $club)
            ? Response::allow()
            : Response::deny('Solo el Presidente del Club puede consultar los tarjetones de su club.');
    }

    public function view(User $user, TarjetonDigital $tarjeton): Response
    {
        return $user->hasRole('Presidente del Club')
            && $user->club_id !== null
            && (int) $user->club_id === (int) $tarjeton->club_id
            && $user->can('tarjetones.ver')
                ? Response::allow()
                : Response::deny('No tiene autorización para consultar este tarjetón.');
    }

    public function create(User $user, Club $club, Temporada $temporada): Response
    {
        if ($temporada->estado !== 'activa') {
            return Response::deny('El tarjetón solo se puede generar para una temporada activa.');
        }

        return $user->hasRole('Presidente del Club')
            && $user->club_id !== null
            && (int) $user->club_id === (int) $club->id
            && $user->can('tarjetones.generar')
                ? Response::allow()
                : Response::deny('Solo el Presidente del Club puede generar el tarjetón de su club.');
    }

    private function presidentePuedeConsultar(User $user, Club $club): bool
    {
        return $user->hasRole('Presidente del Club')
            && $user->club_id !== null
            && (int) $user->club_id === (int) $club->id
            && $user->can('tarjetones.ver');
    }
}
