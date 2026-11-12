<?php

namespace App\Http\Controllers;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Services\CedulaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Cédula arbitral del partido (HU-036). La captura desde el celular llega en el paso 2
class CedulaController extends Controller
{
    public function __construct(private readonly CedulaService $cedulas)
    {
    }

    /**
     * Abre la cédula si el usuario puede capturarla; si solo puede consultarla, muestra la que exista.
     */
    public function show(Partido $partido): View
    {
        Gate::authorize('ver', [CedulaArbitral::class, $partido]);

        $cedula = Gate::allows('capturar', [CedulaArbitral::class, $partido])
            ? $this->cedulas->abrir($partido)
            : $partido->cedula()->first();

        $partido->load(['local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre', 'arbitro.persona:id,nombre,apellidos']);

        // Sin la CURP: de cada persona solo el nombre
        $cedula?->load([
            'alineaciones' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('numero_camiseta'),
            'eventos' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('minuto')->orderBy('id'),
            'incidencias' => fn ($consulta) => $consulta->with('equipo:id,nombre')->orderBy('minuto')->orderBy('id'),
        ]);

        return view('cedulas.show', compact('partido', 'cedula'));
    }
}
