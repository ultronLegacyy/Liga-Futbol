<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Grupo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// Grupos en los que se divide una categoría (HU-007, RF-02.2). Las vistas Blade llegan con S2-T20
class GrupoController extends Controller
{
    /**
     * Agregar un grupo a una categoría.
     */
    public function store(Request $request, Categoria $categoria): RedirectResponse
    {
        Gate::authorize('create', [Grupo::class, $categoria]);

        $datos = $request->validate($this->reglasGrupo($categoria->id), $this->mensajesGrupo());

        // El grupo pertenece a la temporada de su categoría, no a la que se envíe en la petición
        DB::transaction(fn () => $categoria->grupos()->create($datos + [
            'temporada_id' => $categoria->temporada_id,
        ]));

        return redirect()->back()
            ->with('success', 'Grupo registrado correctamente.');
    }

    /**
     * Renombrar un grupo.
     */
    public function update(Request $request, Grupo $grupo): RedirectResponse
    {
        Gate::authorize('update', $grupo);

        $datos = $request->validate($this->reglasGrupo($grupo->categoria_id, $grupo->id), $this->mensajesGrupo());

        DB::transaction(fn () => $grupo->update($datos));

        return redirect()->back()
            ->with('success', 'Grupo actualizado correctamente.');
    }

    /**
     * Reglas del alta y la edición: el nombre no se repite dentro de la misma categoría.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglasGrupo(int $categoriaId, ?int $ignorar = null): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grupos', 'nombre')
                    ->where('categoria_id', $categoriaId)
                    ->ignore($ignorar),
            ],
        ];
    }

    /**
     * Mensajes en español mientras no exista la traducción general de la validación (IU-03).
     *
     * @return array<string, string>
     */
    private function mensajesGrupo(): array
    {
        return [
            'nombre.required' => 'El nombre del grupo es obligatorio.',
            'nombre.string' => 'El nombre del grupo debe ser texto.',
            'nombre.max' => 'El nombre del grupo no debe tener más de :max caracteres.',
            'nombre.unique' => 'Ya existe un grupo con ese nombre en esta categoría.',
        ];
    }
}
