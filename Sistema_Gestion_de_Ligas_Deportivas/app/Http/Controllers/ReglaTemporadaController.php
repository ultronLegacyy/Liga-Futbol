<?php

namespace App\Http\Controllers;

use App\Models\Temporada;
use App\Services\ReglasTemporadaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;

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
        Request $request,
        Temporada $temporada
    ): RedirectResponse {
        abort_unless(
            auth()->user()?->can('reglas_temporada.editar'),
            403
        );

        if ($temporada->estado === 'cerrada') {
            abort(403, 'No se pueden modificar las reglas de una temporada cerrada.');
        }

        $datos = $request->validate([
            'reglas_json' => ['required', 'string', 'max:20000'],
            'motivo' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $reglas = json_decode(
                $datos['reglas_json'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'reglas_json' => 'El contenido debe ser un JSON válido.',
            ]);
        }

        $validador = Validator::make(
            ['reglas' => $reglas],
            ['reglas' => ['required', 'array', 'min:1']]
        );

        if ($validador->fails()) {
            throw ValidationException::withMessages([
                'reglas_json' => 'Las reglas deben ser un objeto JSON válido y no estar vacío.',
            ]);
        }

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