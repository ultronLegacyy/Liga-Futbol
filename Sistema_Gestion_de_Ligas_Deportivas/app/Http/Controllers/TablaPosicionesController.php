<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Services\TablaPosicionesService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// HU-041 (RF-09.1): consulta de la tabla de posiciones de un grupo, con el mismo permiso que el grupo
class TablaPosicionesController extends Controller
{
    public function __construct(private readonly TablaPosicionesService $tablas)
    {
    }

    public function show(Grupo $grupo): View
    {
        Gate::authorize('view', $grupo);

        $grupo->load('categoria:id,nombre', 'temporada:id,nombre,estado');
        $tabla = $this->tablas->tabla($grupo);

        $regla = $this->tablas->reglaDeLaTabla($grupo, $tabla->first()?->regla_version);

        return view('tablas.show', [
            'grupo' => $grupo,
            'tabla' => $tabla,
            'regla' => $regla,
            'criterios' => $regla === null ? [] : $this->tablas->criteriosDeDesempate($regla),
        ]);
    }
}
