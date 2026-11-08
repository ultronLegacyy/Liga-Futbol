<?php

namespace App\Policies;

use App\Models\CargoFinanciero;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Cuotas de inscripción y cargos de arbitraje y cancha (HU-050, RF-13.1); los registra el Tesorero
class CargoFinancieroPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Consultar el listado de cargos
    public function viewAny(User $usuario): Response
    {
        return $usuario->can('finanzas.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar las cuotas y cargos.');
    }

    // Consultar el detalle de un cargo
    public function view(User $usuario, CargoFinanciero $cargo): Response
    {
        return $usuario->can('finanzas.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar las cuotas y cargos.');
    }

    // Registrar una cuota de inscripción o un cargo de arbitraje o cancha
    public function create(User $usuario): Response
    {
        return $usuario->can('finanzas.cargos')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para registrar cuotas y cargos.');
    }
}
