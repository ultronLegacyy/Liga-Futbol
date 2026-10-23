<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClubPolicy
{
    // Evaluacion previa para conceder acceso irrestricto al Administrador General
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Consulta general de la totalidad de clubes (gobernanza)
    public function viewAny(User $usuario): bool
    {
        return $usuario->hasRole('Administrador General') || $usuario->can('clubes.ver');
    }

    // Mitigacion de ID Tampering segun requisito RNF-29: un directivo solo puede consultar su propio club
    public function view(User $usuario, Club $club): Response
    {
        if ($usuario->hasRole('Administrador General')) {
            return Response::allow();
        }

        if ($usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club'])) {
            return $usuario->club_id !== null && (int) $usuario->club_id === (int) $club->id
                ? Response::allow()
                : Response::deny('Acceso denegado: no cuenta con autorizacion para consultar la informacion de otros clubes deportivos.');
        }

        return Response::deny('No posee privilegios para visualizar la informacion de este club deportivo.');
    }

    // Mitigacion de ID Tampering para modificacion: un directivo solo puede editar su propio club
    public function update(User $usuario, Club $club): Response
    {
        if ($usuario->hasRole('Administrador General')) {
            return Response::allow();
        }

        if ($usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club'])) {
            return $usuario->club_id !== null && (int) $usuario->club_id === (int) $club->id
                ? Response::allow()
                : Response::deny('Acceso denegado: intento de modificacion no autorizada sobre un club ajeno.');
        }

        return Response::deny('No posee privilegios para modificar la informacion de este club deportivo.');
    }

    // Eliminacion de clubes restringida a la administracion general
    public function delete(User $usuario, Club $club): Response
    {
        return $usuario->hasRole('Administrador General')
            ? Response::allow()
            : Response::deny('Acceso denegado: la eliminacion de clubes es exclusiva del Administrador General.');
    }
}
