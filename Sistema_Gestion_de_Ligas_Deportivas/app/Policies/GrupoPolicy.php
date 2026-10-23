<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Los grupos son parte de la estructura de la categoría: se administran con el permiso categorias.editar
class GrupoPolicy
{
    // Agregar un grupo a una categoría, salvo que su temporada esté cerrada (RF-01.4)
    public function create(User $usuario, Categoria $categoria): Response
    {
        $temporada = $categoria->temporada;

        if ($temporada === null) {
            return Response::deny('La categoría no pertenece a ninguna temporada.');
        }

        if ($temporada->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus grupos.');
        }

        return $usuario->can('categorias.editar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para administrar grupos.');
    }

    // Renombrar un grupo, salvo que su temporada esté cerrada
    public function update(User $usuario, Grupo $grupo): Response
    {
        if ($grupo->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus grupos.');
        }

        return $usuario->can('categorias.editar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para administrar grupos.');
    }
}
