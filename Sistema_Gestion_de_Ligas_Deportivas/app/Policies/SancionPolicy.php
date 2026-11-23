<?php

namespace App\Policies;

use App\Models\Sancion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Control de acceso y autorización para dictamen y validación de sanciones (HU-045, HU-046, HU-047).
 */
class SancionPolicy
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
        return $usuario->can('disciplina.expedientes')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar las sanciones disciplinarias.');
    }

    public function view(User $usuario, Sancion $sancion): Response
    {
        return $usuario->can('disciplina.expedientes')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar esta sanción disciplinaria.');
    }

    /**
     * Acceso al boletín interno según rol (PO #45):
     * - Tribunal de Penas / Administrador General: vista completa.
     * - Presidente / Delegado del Club: solo sanciones validadas de su club.
     * - Jugador: solo sus propias sanciones validadas.
     */
    public function verBoletin(User $usuario): Response
    {
        if ($usuario->can('disciplina.sancionar')) {
            return Response::allow();
        }

        if ($usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club'])) {
            return $usuario->club_id !== null
                ? Response::allow()
                : Response::deny('Su usuario requiere un club asignado para consultar el boletín disciplinario del club.');
        }

        if ($usuario->hasRole('Jugador')) {
            return $usuario->persona_id !== null
                ? Response::allow()
                : Response::deny('Su usuario requiere estar vinculado a una persona para consultar sus sanciones disciplinarias.');
        }

        return Response::deny('No cuenta con privilegios para consultar el boletín interno de sanciones.');
    }

    public function create(User $usuario): Response
    {
        return $usuario->can('disciplina.sancionar')
            ? Response::allow()
            : Response::deny('Solo los integrantes del Tribunal de Penas o Administrador General pueden dictaminar sanciones disciplinarias.');
    }

    public function validar(User $usuario, Sancion $sancion): Response
    {
        return $usuario->can('disciplina.sancionar')
            ? Response::allow()
            : Response::deny('Solo los integrantes del Tribunal de Penas o Administrador General pueden validar formalmente sanciones.');
    }

    public function publicar(User $usuario): Response
    {
        return $usuario->can('disciplina.sancionar')
            ? Response::allow()
            : Response::deny('Solo los integrantes del Tribunal de Penas o Administrador General pueden autorizar la publicación en el boletín oficial.');
    }
}
