<?php

namespace App\Policies;

use App\Models\Cancha;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// El Administrador General gestiona los catálogos maestros; el Programador de Partidos recibe los permisos con HU-072
class CanchaPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Consultar el catálogo de canchas
    public function viewAny(User $usuario): Response
    {
        return $usuario->can('campos.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar las canchas.');
    }

    // Consultar una cancha
    public function view(User $usuario, Cancha $cancha): Response
    {
        return $this->viewAny($usuario);
    }

    // Registrar una cancha nueva
    public function create(User $usuario): Response
    {
        return $usuario->can('campos.crear')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para registrar canchas.');
    }

    // Cambiar las características o la capacidad de una cancha
    public function update(User $usuario, Cancha $cancha): Response
    {
        return $usuario->can('campos.editar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para modificar canchas.');
    }

    // Registrar o gestionar bloqueos de cancha (HU-024, RF-05.2)
    public function block(User $usuario, Cancha $cancha): Response
    {
        return $usuario->can('campos.bloquear')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para gestionar bloqueos de canchas.');
    }
}
