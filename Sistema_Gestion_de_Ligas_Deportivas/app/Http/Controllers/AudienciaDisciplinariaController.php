<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAudienciaRequest;
use App\Models\AudienciaDisciplinaria;
use App\Models\ExpedienteDisciplinario;
use App\Services\DisciplinaResolucionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador para registro de audiencias disciplinarias (HU-045 / RF-10.2).
 */
class AudienciaDisciplinariaController extends Controller
{
    public function __construct(
        private readonly DisciplinaResolucionService $resolucionService
    ) {
    }

    /**
     * Registra una comparecencia/audiencia en el expediente.
     */
    public function store(StoreAudienciaRequest $request, ExpedienteDisciplinario $expediente): RedirectResponse
    {
        Gate::authorize('create', AudienciaDisciplinaria::class);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validated();
        $motivo = $validated['motivo'] ?? "Audiencia celebrada para expediente {$expediente->folio}";

        $this->resolucionService->registrarAudiencia($expediente, $validated, $usuario, $motivo);

        return redirect()
            ->route('disciplina.expedientes.show', $expediente)
            ->with('success', 'Audiencia formal registrada exitosamente.');
    }
}
