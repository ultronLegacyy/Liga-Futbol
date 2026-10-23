<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CategoriaPolicy
{
    // El Administrador General consulta todo; los cambios se revisan en cada método para respetar la temporada cerrada (RF-01.4)
    public function before(User $usuario, string $habilidad): ?bool
    {
        if (in_array($habilidad, ['create', 'update'], true)) {
            return null;
        }

        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Consultar las categorías de una temporada
    public function viewAny(User $usuario): Response
    {
        return $usuario->can('categorias.ver')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para consultar las categorías.');
    }

    // Consultar una categoría con sus grupos
    public function view(User $usuario, Categoria $categoria): Response
    {
        return $this->viewAny($usuario);
    }

    // Agregar una categoría a la temporada, salvo que esté cerrada
    public function create(User $usuario, Temporada $temporada): Response
    {
        if ($temporada->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus categorías.');
        }

        return $usuario->can('categorias.crear')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para registrar categorías.');
    }

    // Renombrar o cambiar los datos de una categoría, salvo que su temporada esté cerrada
    public function update(User $usuario, Categoria $categoria): Response
    {
        if ($categoria->temporada?->estado === 'cerrada') {
            return Response::deny('Una temporada cerrada no admite cambios en sus categorías.');
        }

        return $usuario->can('categorias.editar')
            ? Response::allow()
            : Response::deny('No cuenta con privilegios para modificar categorías.');
    }
}
