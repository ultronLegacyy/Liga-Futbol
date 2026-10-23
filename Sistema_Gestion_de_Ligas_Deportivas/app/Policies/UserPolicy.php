<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    // Evaluacion previa para conceder acceso irrestricto al Administrador General salvo sobre su propia cuenta
    public function before(User $usuarioAutenticado, string $habilidad): ?bool
    {
        // Eliminar, desactivar y cambiar roles se revisan en su metodo: nadie puede hacerlo sobre su propia cuenta
        if (in_array($habilidad, ['delete', 'toggleStatus', 'asignarRoles'], true)) {
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

    // Desactivar o reactivar una cuenta (HU-080, CA6): nadie se desactiva a si mismo
    public function toggleStatus(User $usuarioAutenticado, User $usuarioObjetivo): Response
    {
        if ($usuarioAutenticado->id === $usuarioObjetivo->id) {
            return Response::deny('No es posible desactivar o reactivar la propia cuenta.');
        }

        return $usuarioAutenticado->can('usuarios.editar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para desactivar o reactivar cuentas de usuario.');
    }

    // Asignar o retirar roles (HU-080, CA5): nadie cambia sus propios roles, asi el sistema no se queda sin Administrador General
    public function asignarRoles(User $usuarioAutenticado, User $usuarioObjetivo): Response
    {
        if ($usuarioAutenticado->id === $usuarioObjetivo->id) {
            return Response::deny('No es posible cambiar los roles de la propia cuenta.');
        }

        return $usuarioAutenticado->can('usuarios.asignar_rol')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para asignar roles.');
    }
}
