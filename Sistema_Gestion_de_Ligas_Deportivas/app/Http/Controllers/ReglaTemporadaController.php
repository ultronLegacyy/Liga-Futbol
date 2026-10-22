<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReglasTemporadaRequest;
use App\Models\Temporada;
use App\Services\ReglasTemporadaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReglaTemporadaController extends Controller
{
    public function __construct(
        private readonly ReglasTemporadaService $reglasService
    ) {
    }

    public function index(Temporada $temporada): View
    {
        abort_unless(
            auth()->user()?->can('reglas_temporada.ver'),
            403
        );

        $reglas = $this->reglasService->historial($temporada);

        return view('reglas_temporada.index', compact('temporada', 'reglas'));
    }

    public function store(
        StoreReglasTemporadaRequest $request,
        Temporada $temporada
    ): RedirectResponse {
        if ($temporada->estado === 'cerrada') {
            abort(403, 'No se pueden modificar las reglas de una temporada cerrada.');
        }

        $datos = $request->validated();

        $reglas = json_decode(
            $datos['reglas_json'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->reglasService->guardar(
            $temporada,
            $reglas,
            $datos['motivo']
        );

        return redirect()
            ->route('temporadas.reglas.index', $temporada)
            ->with('success', 'Nueva versión de reglas guardada correctamente.');
    }
}
