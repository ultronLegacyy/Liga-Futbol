<?php

namespace App\Http\Controllers;

use App\Http\Requests\DictaminarSancionRequest;
use App\Models\ExpedienteDisciplinario;
use App\Models\Sancion;
use App\Services\DisciplinaResolucionService;
use App\Services\VigenciaSancionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador para dictamen y validación de sanciones disciplinarias (HU-045, HU-046 / RF-10.2, RF-10.3, RNF-44).
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
    public function validar(Request $request, Sancion $sancion): RedirectResponse
    {
        Gate::authorize('validar', $sancion);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->vigenciaService->validarSancion($sancion, $usuario, $validated['motivo']);

        return redirect()
            ->back()
            ->with('success', 'Sanción disciplinaria validada formalmente.');
    }

    /**
     * Publica individualmente una sanción en el boletín oficial de castigados (RNF-44).
     */
    public function publicarBoletin(Request $request, Sancion $sancion): RedirectResponse
    {
        Gate::authorize('publicar', $sancion);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validate([
            'autorizado_por_cargo' => ['nullable', 'string', 'max:150'],
            'motivo' => ['nullable', 'string', 'min:5', 'max:500'],
        ]);

        $motivo = ! empty($validated['motivo']) ? $validated['motivo'] : 'Publicación oficial en boletín semanal de sanciones';
        $cargo = $validated['autorizado_por_cargo'] ?? null;

        $this->vigenciaService->publicarEnBoletin([$sancion->id], $usuario, $motivo, $cargo);

        return redirect()
            ->back()
            ->with('success', 'Sanción disciplinaria publicada exitosamente en el boletín oficial.');
    }

    /**
     * Retira individualmente una sanción del boletín oficial.
     */
    public function despublicarBoletin(Request $request, Sancion $sancion): RedirectResponse
    {
        Gate::authorize('publicar', $sancion);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'min:5', 'max:500'],
        ]);

        $motivo = ! empty($validated['motivo']) ? $validated['motivo'] : 'Retiro de sanción del boletín oficial';
        $this->vigenciaService->despublicarDeBoletin($sancion, $usuario, $motivo);

        return redirect()
            ->back()
            ->with('success', 'Sanción retirada del boletín oficial de castigados.');
    }
}
