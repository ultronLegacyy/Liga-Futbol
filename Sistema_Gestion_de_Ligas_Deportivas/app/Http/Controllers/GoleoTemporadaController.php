<?php

namespace App\Http\Controllers;

use App\Models\Temporada;
use App\Services\EstadisticasGrupoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// HU-060 CA3 (RF-11.6): campeón de goleo de cada categoría de la temporada; mientras no se cierra, es el líder provisional
class GoleoTemporadaController extends Controller
{
    public function __construct(private readonly EstadisticasGrupoService $estadisticas)
    {
    }

    public function show(Temporada $temporada): View
    {
        Gate::authorize('verGoleo', $temporada);

        return view('temporadas.goleo', [
            'temporada' => $temporada,
            'cerrada' => $temporada->estado === 'cerrada',
            'campeones' => $this->estadisticas->campeonesDeGoleo($temporada),
        ]);
    }
}
