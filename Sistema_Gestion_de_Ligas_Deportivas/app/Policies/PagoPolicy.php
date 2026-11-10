<?php

namespace App\Policies;

use App\Models\Pago;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Pagos de los cargos y sus comprobantes (HU-051, RF-13.2); los registra el Tesorero
class PagoPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Registrar un pago con su comprobante
    public function create(User $usuario): Response
    {
        return $usuario->can('finanzas.pagos_validar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para registrar pagos.');
    }

    // Consultar y descargar el comprobante de un pago
    public function view(User $usuario, Pago $pago): Response
    {
        return $usuario->can('finanzas.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar los comprobantes de pago.');
    }
}
