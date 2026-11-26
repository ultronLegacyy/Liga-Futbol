<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\PropuestaAscensoDescenso;
use App\Models\User;
use App\Services\PropuestaAscensoDescensoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// HU-057 (RF-11.3): propuestas de ascenso y descenso de un grupo; consultar pide el permiso del grupo
class PropuestaAscensoDescensoController extends Controller
{
    public function __construct(private readonly PropuestaAscensoDescensoService $propuestas)
    {
    }

    public function show(Request $request, Grupo $grupo): View
    {
        Gate::authorize('view', $grupo);

        $grupo->load('categoria:id,nombre', 'temporada');

        return view('ascenso_descenso.show', [
            'grupo' => $grupo,
            'revision' => $this->propuestas->revisar($grupo),
            'versiones' => $this->propuestas->versiones($grupo),
            'puedeGenerar' => $request->user()?->can('create', [PropuestaAscensoDescenso::class, $grupo]) ?? false,
        ]);
    }

    public function store(Request $request, Grupo $grupo): RedirectResponse
    {
        Gate::authorize('create', [PropuestaAscensoDescenso::class, $grupo]);

        /** @var User $usuario */
        $usuario = $request->user();
        $version = $this->propuestas->generar($grupo, $usuario);

        return redirect()
            ->route('grupos.ascenso-descenso', $grupo)
            ->with('success', "Se guardó la versión {$version} de la propuesta de ascenso y descenso.");
    }
}
