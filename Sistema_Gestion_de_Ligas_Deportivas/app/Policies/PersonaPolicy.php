<?php

namespace App\Policies;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Padrón de personas (HU-013, RF-03): el Secretario lo administra; el Delegado del Club lo recibe con HU-016
class PersonaPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Consultar el padrón de personas
    public function viewAny(User $usuario): Response
    {
        return $usuario->can('personas.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar personas.');
    }

    // Consultar una persona y sus roles por temporada
    public function view(User $usuario, Persona $persona): Response
    {
        return $this->viewAny($usuario);
    }

    // Registrar una persona o asignarle un rol en una temporada
    public function create(User $usuario): Response
    {
        return $usuario->can('personas.crear')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para registrar personas.');
    }
}
