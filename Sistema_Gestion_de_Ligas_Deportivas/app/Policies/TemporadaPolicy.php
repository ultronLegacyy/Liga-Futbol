<?php

namespace App\Policies;

use App\Models\Temporada;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TemporadaPolicy
{
    // Evaluacion previa para otorgar acceso irrestricto al Administrador General salvo en operaciones de cierre
    public function before(User $usuario, string $habilidad): ?bool
    {
        // Se delega a los metodos update y delete para verificar la inmutabilidad de temporadas cerradas
        if (in_array($habilidad, ['update', 'delete'])) {
            return null;
        }

        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Autorizacion para consultar el listado general de temporadas
    public function viewAny(User $usuario): bool
    {
        return $usuario->can('temporadas.ver');
    }

    // Autorizacion para consultar el detalle de una temporada
    public function view(User $usuario, Temporada $temporada): bool
    {
        return $usuario->can('temporadas.ver');
    }

    // Autorizacion para crear una nueva temporada deportiva
    public function create(User $usuario): bool
    {
        return $usuario->can('temporadas.crear');
    }

    // Autorizacion para actualizar una temporada verificando que no se encuentre cerrada
    public function update(User $usuario, Temporada $temporada): Response
    {
        if ($temporada->estado === 'cerrada') {
            return Response::deny('Error: Una temporada cerrada no puede modificarse.');
        }

        return $usuario->can('temporadas.editar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para modificar temporadas.');
    }

    // Autorizacion para eliminar una temporada verificando que no se encuentre cerrada
    public function delete(User $usuario, Temporada $temporada): Response
    {
        if ($temporada->estado === 'cerrada') {
            return Response::deny('Error: Una temporada cerrada no puede eliminarse.');
        }

        return $usuario->can('temporadas.eliminar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para eliminar temporadas.');
    }
}
