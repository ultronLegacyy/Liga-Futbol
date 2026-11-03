<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EquipoPolicy
{
    // Evaluacion previa para conceder acceso irrestricto al Administrador General
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    public function create(User $usuario): Response
    {
        return $usuario->hasRole('Delegado del Club')
            && $usuario->club_id !== null
            && $usuario->can('equipos.crear')
                ? Response::allow()
                : Response::deny('Solo el Delegado del Club con permiso y club asignado puede registrar equipos.');
    }

    public function addPlayer(User $usuario, Equipo $equipo): Response
    {
        return $usuario->hasRole('Delegado del Club')
            && $usuario->club_id !== null
            && (int) $usuario->club_id === (int) $equipo->club_id
            && $usuario->can('jugadores.crear')
                ? Response::allow()
                : Response::deny('Solo el Delegado del Club puede inscribir jugadores en equipos de su club.');
    }

    // Mitigacion de ID Tampering: un directivo solo puede consultar equipos de su club adscrito
    public function view(User $usuario, Equipo $equipo): Response
    {
        if ($usuario->hasRole('Administrador General')) {
            return Response::allow();
        }

        if ($usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club'])) {
            return $usuario->club_id !== null && (int) $usuario->club_id === (int) $equipo->club_id
                ? Response::allow()
                : Response::deny('Acceso denegado: no cuenta con autorizacion para consultar equipos de otros clubes.');
        }

        return Response::deny('No posee privilegios para visualizar este equipo.');
    }

    // Mitigacion de ID Tampering: modificacion restringida al club propietario
    public function update(User $usuario, Equipo $equipo): Response
    {
        if ($usuario->hasRole('Administrador General')) {
            return Response::allow();
        }

        if ($usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club'])) {
            return $usuario->club_id !== null && (int) $usuario->club_id === (int) $equipo->club_id
                ? Response::allow()
                : Response::deny('Acceso denegado: intento de modificacion no autorizada sobre un equipo de otro club.');
        }

        return Response::deny('No posee privilegios para modificar este equipo.');
    }
}
