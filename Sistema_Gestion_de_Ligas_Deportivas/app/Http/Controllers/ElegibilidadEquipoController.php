<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Services\ElegibilidadJugadorService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// HU-019 CA3: elegibilidad de los jugadores de un equipo, para que el Delegado y el Secretario se enteren antes del partido
class ElegibilidadEquipoController extends Controller
{
    public function __construct(private readonly ElegibilidadJugadorService $elegibilidad)
    {
    }

    public function show(Equipo $equipo): View
    {
        Gate::authorize('verElegibilidad', $equipo);

        $equipo->load('club:id,nombre', 'grupo.categoria:id,nombre');

        $jugadores = Jugador::query()
            ->where('equipo_id', $equipo->id)
            ->where('estado_elegibilidad', '!=', 'Baja')
            ->with('persona:id,nombre,apellidos')
            ->orderBy('dorsal')
            ->get()
            ->map(fn (Jugador $jugador) => ['jugador' => $jugador, ...$this->elegibilidad->evaluar($jugador)]);

        return view('equipos.elegibilidad', [
            'equipo' => $equipo,
            'jugadores' => $jugadores,
        ]);
    }
}
