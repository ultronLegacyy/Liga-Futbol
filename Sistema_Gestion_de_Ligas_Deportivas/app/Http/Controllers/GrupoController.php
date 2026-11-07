<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Grupo;
use App\Models\Temporada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Grupos en los que se divide una categoría (HU-007, RF-02.2): se agregan desde la ficha de la categoría y se renombran en su propio formulario
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

        return redirect()->route('categorias.show', $categoria)
            ->with('success', 'Grupo registrado correctamente.');
    }

    /**
     * Formulario para renombrar un grupo.
     */
    public function edit(Grupo $grupo): View
    {
        Gate::authorize('update', $grupo);

        $grupo->load('categoria.temporada');

        return view('grupos.edit', compact('grupo'));
    }

    /**
     * Renombrar un grupo.
     */
    public function update(Request $request, Grupo $grupo): RedirectResponse
    {
        Gate::authorize('update', $grupo);

        $datos = $request->validate($this->reglasGrupo($grupo->categoria_id, $grupo->id), $this->mensajesGrupo());

        DB::transaction(fn () => $grupo->update($datos));

        return redirect()->route('categorias.show', $grupo->categoria_id)
            ->with('success', 'Grupo actualizado correctamente.');
    }

    /**
     * Consultar el formato de competencia y los criterios de clasificación del grupo (HU-008).
     * En una temporada cerrada se muestran sin formulario (RF-01.4).
     */
    public function configuracion(Request $request, Grupo $grupo): View|JsonResponse
    {
        Gate::authorize('view', $grupo);

        $grupo->load('categoria.temporada', 'temporada');

        if ($request->expectsJson()) {
            return response()->json([
                'modulo' => 'Grupo',
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'formato_competencia' => $grupo->formato_competencia,
                'criterios_clasificacion' => $grupo->criterios_clasificacion,
            ]);
        }

        return view('grupos.configuracion', compact('grupo'));
    }

    /**
     * Guardar o modificar el formato de competencia y los criterios de clasificación (HU-008, CA1 a CA3).
     */
    public function guardarConfiguracion(Request $request, Grupo $grupo): RedirectResponse
    {
        Gate::authorize('update', $grupo);

        $datos = $request->validate([
            'formato_competencia' => ['required', Rule::in(array_keys(Grupo::FORMATOS))],
            'criterios_clasificacion' => ['required', 'array:'.implode(',', array_keys(Grupo::CRITERIOS))],
            'criterios_clasificacion.clasifican_liguilla' => ['required', 'integer', 'min:0'],
            'criterios_clasificacion.clasifican_consolacion' => ['required', 'integer', 'min:0'],
        ], [
            'formato_competencia.required' => 'El formato de competencia es obligatorio.',
            'formato_competencia.in' => 'El formato de competencia no es válido.',
            'criterios_clasificacion.required' => 'Los criterios de clasificación son obligatorios.',
            'criterios_clasificacion.array' => 'Los criterios de clasificación no son válidos.',
            'criterios_clasificacion.*.required' => 'Indica cuántos equipos clasifican.',
            'criterios_clasificacion.*.integer' => 'La cantidad de equipos debe ser un número entero.',
            'criterios_clasificacion.*.min' => 'La cantidad de equipos no puede ser negativa.',
        ]);

        DB::transaction(function () use ($grupo, $datos): void {
            // La temporada no se cierra mientras se guarda; si ya se cerró, se rechaza el cambio
            $temporada = Temporada::query()->whereKey($grupo->temporada_id)->sharedLock()->firstOrFail();
            Gate::authorize('update', $grupo->setRelation('temporada', $temporada));

            $grupo->update([
                'formato_competencia' => $datos['formato_competencia'],
                'criterios_clasificacion' => array_map('intval', $datos['criterios_clasificacion']),
            ]);
        });

        return redirect()->route('grupos.configuracion', $grupo)
            ->with('success', 'Formato y criterios de clasificación guardados.');
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
