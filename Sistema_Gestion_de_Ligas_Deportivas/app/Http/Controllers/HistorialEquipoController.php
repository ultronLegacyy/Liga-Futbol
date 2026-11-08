<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Services\HistorialTemporadasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Historial de los equipos a través de las temporadas (HU-015, RF-03.4); solo consulta
class HistorialEquipoController extends Controller
{
    public function __construct(
        private readonly HistorialTemporadasService $historial
    ) {
    }

    /**
     * Listar los equipos (club y nombre) con cuántas temporadas han participado.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAnyHistorial', Equipo::class);

        return view('equipos.historial.index', [
            'equipos' => $this->historial->equipos($request->user()),
        ]);
    }

    /**
     * Temporadas, categorías y grupos en los que participó el equipo (CA1).
     */
    public function show(Equipo $equipo): View
    {
        Gate::authorize('viewHistorial', $equipo);

        $equipo->load('club:id,nombre');

        return view('equipos.historial.show', [
            'equipo' => $equipo,
            'participaciones' => $this->historial->historialEquipo($equipo),
        ]);
    }
}
