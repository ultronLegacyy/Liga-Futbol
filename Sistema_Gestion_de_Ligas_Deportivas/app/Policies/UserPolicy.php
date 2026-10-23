<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    // Evaluacion previa para conceder acceso irrestricto al Administrador General salvo en autoeliminacion
    public function before(User $usuarioAutenticado, string $habilidad): ?bool
    {
        // Se delega a delete para verificar que ningun usuario pueda autoeliminarse
        if ($habilidad === 'delete') {
            return null;
        }

        if ($usuarioAutenticado->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Consulta de lista completa de usuarios
    public function viewAny(User $usuarioAutenticado): bool
    {
        return $usuarioAutenticado->can('usuarios.ver');
    }

    // Consulta del perfil de un usuario especifico
    public function view(User $usuarioAutenticado, User $usuarioObjetivo): bool
    {
        return $usuarioAutenticado->id === $usuarioObjetivo->id || $usuarioAutenticado->can('usuarios.ver');
    }

    // Creacion de nuevos usuarios en el sistema
    public function create(User $usuarioAutenticado): bool
    {
        return $usuarioAutenticado->can('usuarios.crear');
    }

    // Modificacion de datos de usuario
    public function update(User $usuarioAutenticado, User $usuarioObjetivo): bool
    {
        return $usuarioAutenticado->id === $usuarioObjetivo->id || $usuarioAutenticado->can('usuarios.editar');
    }

    // Eliminacion de cuentas de usuario
    public function delete(User $usuarioAutenticado, User $usuarioObjetivo): Response
    {
        if ($usuarioAutenticado->id === $usuarioObjetivo->id) {
            return Response::deny('No es posible eliminar la propia cuenta de usuario en sesion activa.');
        }

        return $usuarioAutenticado->can('usuarios.eliminar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para eliminar cuentas de usuario.');
    }
    public function toggleStatus(User $user, User $model): bool
    {
        // Evitar que el administrador se desactive a sí mismo (como pidió el jefe)
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasRole('Administrador General');
    }
}
