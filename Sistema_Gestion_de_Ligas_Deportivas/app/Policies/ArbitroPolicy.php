<?php

namespace App\Policies;

use App\Models\Arbitro;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Padrón arbitral y asignaciones (HU-026); la Comisión Arbitral recibe los permisos cuando se confirme (Pendiente HU-010 en la matriz)
class ArbitroPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Consultar el padrón de árbitros
    public function viewAny(User $usuario): Response
    {
        return $usuario->can('arbitros.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar el padrón de árbitros.');
    }

    // Registrar a una persona con rol de árbitro en el padrón
    public function create(User $usuario): Response
    {
        return $usuario->can('arbitros.crear')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para registrar árbitros.');
    }

    // Cambiar colegio, tarifa o disponibilidad de un árbitro
    public function update(User $usuario, Arbitro $arbitro): Response
    {
        return $usuario->can('arbitros.editar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para modificar árbitros.');
    }

    // Asignar árbitros a los partidos programados
    public function asignar(User $usuario): Response
    {
        return $usuario->can('arbitros.asignar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para asignar árbitros.');
    }
}
