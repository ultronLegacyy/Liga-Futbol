<?php

namespace App\Http\Controllers;

use App\Models\CedulaArbitral;
use App\Models\User;
use App\Services\ValidacionResultadoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HU-040 (RF-08.4): validar oficialmente los resultados capturados. La ruta exige resultados.validar
class ResultadoController extends Controller
{
    public function __construct(private readonly ValidacionResultadoService $validacion)
    {
    }

    // Resultados con la cédula cerrada que esperan validación y los últimos ya oficiales
    public function index(): View
    {
        return view('resultados.index', [
            'pendientes' => $this->validacion->pendientes(),
            'recientes' => $this->validacion->recientes(),
        ]);
    }

    // CA2: el resultado pasa a oficial y dispara el cálculo de la tabla (HU-041)
    public function validar(Request $request, CedulaArbitral $cedula): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $this->validacion->validar($cedula, $usuario);

        $partido = $cedula->partido()->with(['local:id,nombre', 'visitante:id,nombre'])->first();

        return redirect()
            ->route('resultados.index')
            ->with('success', 'Resultado validado: '.$partido?->local?->nombre.' '.$cedula->goles_local.' - '.$cedula->goles_visitante.' '.$partido?->visitante?->nombre.'. Ya es oficial.');
    }
}
