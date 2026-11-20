<?php

namespace App\Policies;

use App\Models\AudienciaDisciplinaria;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Control de acceso y autorización para audiencias disciplinarias (HU-045 / RF-10.2).
 */
class AudienciaDisciplinariaPolicy
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
            : Response::deny('No cuenta con privilegios para consultar audiencias disciplinarias.');
    }

    public function view(User $usuario, AudienciaDisciplinaria $audiencia): Response
    {
        return $usuario->can('disciplina.ver') || $usuario->can('disciplina.expedientes')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar esta audiencia disciplinaria.');
    }

    public function create(User $usuario): Response
    {
        return $usuario->can('disciplina.sancionar')
            ? Response::allow()
            : Response::deny('Solo los integrantes del Tribunal de Penas o Administrador General pueden registrar audiencias disciplinarias.');
    }
}
