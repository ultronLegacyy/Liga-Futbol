<?php

namespace App\Policies;

use App\Models\ExpedienteDisciplinario;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Control de acceso y autorización para expedientes disciplinarios (HU-044 / RF-10.1).
 * Privilegios reservados al Tribunal de Penas / Comisión Disciplinaria y Administrador General.
 */
class ExpedienteDisciplinarioPolicy
{
    /**
     * El Administrador General dispone de acceso integral a la gestión disciplinaria.
     */
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    /**
     * Determina si el usuario puede listar expedientes disciplinarios.
     */
    public function viewAny(User $usuario): Response
    {
        return $usuario->can('disciplina.expedientes')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar el registro de expedientes disciplinarios.');
    }

    /**
     * Determina si el usuario puede consultar el detalle de un expediente disciplinario.
     */
    public function view(User $usuario, ExpedienteDisciplinario $expediente): Response
    {
        return $usuario->can('disciplina.expedientes')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar este expediente disciplinario.');
    }

    /**
     * Determina si el usuario puede imputar o desestimar artículos presuntos en el expediente.
     */
    public function gestionar(User $usuario, ExpedienteDisciplinario $expediente): Response
    {
        return $usuario->can('disciplina.expedientes')
            ? Response::allow()
            : Response::deny('Solo los integrantes del Tribunal de Penas pueden gestionar los artículos del expediente disciplinario.');
    }
}
