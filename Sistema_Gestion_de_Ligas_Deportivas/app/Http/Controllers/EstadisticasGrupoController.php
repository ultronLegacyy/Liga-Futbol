<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Jornada;
use App\Services\EstadisticasGrupoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// HU-043 (RF-09.3, RF-09.4): estadísticas del grupo y tabla histórica por jornada, con el permiso para consultar el grupo
class EstadisticasGrupoController extends Controller
{
    public function __construct(private readonly EstadisticasGrupoService $estadisticas)
    {
    }

    public function show(Request $request, Grupo $grupo): View
    {
        Gate::authorize('view', $grupo);

        $grupo->load('categoria:id,nombre', 'temporada:id,nombre');
        $jornadas = $this->estadisticas->jornadasConHistorico($grupo);

        // Por omisión se muestra la copia de la jornada más reciente; ?jornada= elige otra del mismo grupo
        $elegida = $request->integer('jornada');
        $jornada = $elegida > 0 ? $jornadas->firstWhere('id', $elegida) : $jornadas->last();
        abort_if($elegida > 0 && $jornada === null, 404);

        return view('estadisticas.show', [
            'grupo' => $grupo,
            'resultados' => $this->estadisticas->resultadosOficiales($grupo),
            'goleadores' => $this->estadisticas->goleadores($grupo),
            'tarjetas' => $this->estadisticas->tarjetas($grupo),
            'sanciones' => $this->estadisticas->sanciones($grupo),
            'jornadas' => $jornadas,
            'jornada' => $jornada,
            'historico' => $jornada instanceof Jornada ? $this->estadisticas->historico($jornada) : collect(),
        ]);
    }
}
