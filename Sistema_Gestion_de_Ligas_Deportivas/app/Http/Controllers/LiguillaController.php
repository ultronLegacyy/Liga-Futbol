<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\User;
use App\Services\LiguillaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * HU-055 (RF-11.1): clasificación a la liguilla y llaves de un grupo.
 * Consultar pide el permiso del grupo; generar, el de modificar la temporada (Presidente y Administrador General),
 * que además rechaza una temporada cerrada. El rol exacto está pendiente de confirmar con la Liga.
 */
class LiguillaController extends Controller
{
    public function __construct(private readonly LiguillaService $liguilla)
    {
    }

    public function show(Request $request, Grupo $grupo): View
    {
        Gate::authorize('view', $grupo);

        $grupo->load('categoria:id,nombre', 'temporada');
        $llaves = $this->liguilla->llaves($grupo);
        $temporada = $grupo->temporada;

        return view('liguilla.show', [
            'grupo' => $grupo,
            'llaves' => $llaves,
            'revision' => $llaves->isEmpty() ? $this->liguilla->revisar($grupo) : null,
            'puedeGenerar' => $temporada !== null && ($request->user()?->can('update', $temporada) ?? false),
        ]);
    }

    public function store(Request $request, Grupo $grupo): RedirectResponse
    {
        $temporada = $grupo->temporada;
        abort_if($temporada === null, 404);
        Gate::authorize('update', $temporada);

        /** @var User $usuario */
        $usuario = $request->user();
        $llaves = $this->liguilla->generar($grupo, $usuario);

        return redirect()
            ->route('grupos.liguilla', $grupo)
            ->with('success', 'Se generaron '.$llaves->count().' llave(s) de liguilla.');
    }
}
