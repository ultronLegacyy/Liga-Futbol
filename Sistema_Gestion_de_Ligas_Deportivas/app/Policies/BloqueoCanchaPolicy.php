<?php

namespace App\Policies;

use App\Models\BloqueoCancha;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Gestion de bloqueos temporales de canchas (HU-024, RF-05.2)
class BloqueoCanchaPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $usuario): Response
    {
        return $usuario->can('campos.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar los bloqueos de canchas.');
    }

    public function view(User $usuario, BloqueoCancha $bloqueo): Response
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): Response
    {
        return $usuario->can('campos.bloquear')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para registrar bloqueos de canchas.');
    }

    public function delete(User $usuario, BloqueoCancha $bloqueo): Response
    {
        return $usuario->can('campos.bloquear')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para liberar o eliminar bloqueos de canchas.');
    }
}
