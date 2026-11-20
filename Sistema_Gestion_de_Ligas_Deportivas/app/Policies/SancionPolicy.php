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
        return $usuario->can('disciplina.ver') || $usuario->can('disciplina.expedientes')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar las sanciones disciplinarias.');
    }

    public function view(User $usuario, Sancion $sancion): Response
    {
        return $usuario->can('disciplina.ver') || $usuario->can('disciplina.expedientes')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar esta sanción disciplinaria.');
    }

    public function create(User $usuario): Response
    {
        return $usuario->can('disciplina.sancionar')
            ? Response::allow()
            : Response::deny('Solo los integrantes del Tribunal de Penas o Administrador General pueden dictaminar sanciones disciplinarias.');
    }

    public function dictaminar(User $usuario): Response
    {
        return $this->create($usuario);
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
