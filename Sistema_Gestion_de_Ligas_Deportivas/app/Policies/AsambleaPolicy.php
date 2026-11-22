<?php

namespace App\Policies;

use App\Models\Asamblea;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Asambleas de delegados (RF-14): el Secretario (Consejo Directivo) las convoca y registra su asistencia (HU-053)
class AsambleaPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        return $usuario->hasRole('Administrador General') ? true : null;
    }

    public function viewAny(User $usuario): Response
    {
        return $usuario->can('asambleas.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar las asambleas.');
    }

    public function view(User $usuario, Asamblea $asamblea): Response
    {
        return $this->viewAny($usuario);
    }

    // Convocar una asamblea nueva
    public function create(User $usuario): Response
    {
        return $usuario->can('asambleas.convocar')
            ? Response::allow()
            : Response::deny('Solo el Secretario puede convocar una asamblea.');
    }

    // Registrar o corregir la lista de asistencia de los delegados
    public function registrarAsistencia(User $usuario, Asamblea $asamblea): Response
    {
        return $usuario->can('asambleas.asistencia')
            ? Response::allow()
            : Response::deny('Solo el Secretario puede registrar la asistencia a una asamblea.');
    }

    // Registrar acuerdos y comunicados de la asamblea (HU-054, RF-14.2)
    public function registrarAcuerdos(User $usuario, Asamblea $asamblea): Response
    {
        return $usuario->can('asambleas.acuerdos.registrar')
            ? Response::allow()
            : Response::deny('Solo el Secretario puede registrar los acuerdos y comunicados de una asamblea.');
    }

    // Convertir un acuerdo en regla permanente (HU-054, RF-14.3): requiere autorización expresa
    public function autorizarReglaPermanente(User $usuario, Asamblea $asamblea): Response
    {
        return $usuario->can('asambleas.acuerdos.autorizar')
            ? Response::allow()
            : Response::deny('No cuenta con autorización para convertir un acuerdo en regla permanente.');
    }
}
