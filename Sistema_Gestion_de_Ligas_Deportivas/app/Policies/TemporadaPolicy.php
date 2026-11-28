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
        // Se delega a update, delete y programarMasivo para verificar la inmutabilidad de temporadas cerradas
        if (in_array($habilidad, ['update', 'delete', 'programarMasivo'], true)) {
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

    /**
     * Autorización para ejecutar la programación automática masiva sobre una temporada (HU-074 / RF-07.2).
     */
    public function programarMasivo(User $usuario, Temporada $temporada): Response
    {
        if ($temporada->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en su programación.');
        }

        return ($usuario->hasRole('Administrador General') || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para ejecutar la programación automática masiva.');
    }

    /**
     * Autorización para consultar el resumen y estado (completado/cancelado/fallido) de la programación masiva,
     * incluso si la temporada fue cerrada posteriormente (HU-033 / HU-074).
     */
    public function verResumenProgramacionMasiva(User $usuario, Temporada $temporada): Response
    {
        return ($usuario->hasRole('Administrador General') || $usuario->can('partidos.programar'))
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar el resumen de programación masiva.');
    }
}
