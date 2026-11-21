<?php

namespace App\Http\Controllers;

use App\Http\Requests\DictaminarSancionRequest;
use App\Http\Requests\PublicarSancionBoletinRequest;
use App\Http\Requests\ValidarSancionRequest;
use App\Models\ExpedienteDisciplinario;
use App\Models\Sancion;
use App\Services\DisciplinaResolucionService;
use App\Services\VigenciaSancionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador para dictamen, validación y publicación individual de sanciones disciplinarias (HU-045, HU-046 / RF-10.2, RF-10.3, RNF-44).
 */
class SancionController extends Controller
{
    public function __construct(
        private readonly DisciplinaResolucionService $resolucionService,
        private readonly VigenciaSancionService $vigenciaService
    ) {
    }

    /**
     * Dictamina una sanción para el expediente disciplinario.
     */
    public function store(DictaminarSancionRequest $request, ExpedienteDisciplinario $expediente): RedirectResponse
    {
        Gate::authorize('create', Sancion::class);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validated();
        $motivo = $validated['motivo'] ?? "Dictamen de sanción para expediente {$expediente->folio}";

        $this->resolucionService->dictaminarSancion($expediente, $validated, $usuario, $motivo);

        return redirect()
            ->route('disciplina.expedientes.show', $expediente)
            ->with('success', 'Sanción disciplinaria dictaminada y guardada exitosamente.');
    }

    /**
     * Valida formalmente una sanción por la Comisión Disciplinaria (RNF-44).
     */
    public function validar(ValidarSancionRequest $request, Sancion $sancion): RedirectResponse
    {
        Gate::authorize('validar', $sancion);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validated();
        $this->vigenciaService->validarSancion($sancion, $usuario, $validated['motivo']);

        return redirect()
            ->back()
            ->with('success', 'Sanción disciplinaria validada formalmente.');
    }

    /**
     * Publica individualmente una sanción validada en el boletín oficial de castigados (RNF-44).
     */
    public function publicarBoletin(PublicarSancionBoletinRequest $request, Sancion $sancion): RedirectResponse
    {
        Gate::authorize('publicar', $sancion);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validated();
        $motivo = ! empty($validated['motivo']) ? $validated['motivo'] : 'Publicación oficial en boletín semanal de sanciones';

        $this->vigenciaService->publicarEnBoletin([$sancion->id], $usuario, $motivo);

        return redirect()
            ->back()
            ->with('success', 'Sanción disciplinaria publicada exitosamente en el boletín oficial.');
    }
}
