<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJornadaRequest;
use App\Http\Requests\UpdateJornadaRequest;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Services\ProgramacionJornadaService;
use App\Services\PublicacionProgramacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Gestión de jornadas, descansos por grupo y publicación semanal (HU-027, HU-028, HU-030, RF-06.1, RF-06.2, RF-06.5)
class JornadaController extends Controller
{
    public function __construct(
        private readonly ProgramacionJornadaService $programacionService,
        private readonly PublicacionProgramacionService $publicacionService
    ) {
    }

    /**
     * Listado de jornadas de un grupo.
     */
    public function index(Grupo $grupo): View
    {
        Gate::authorize('viewAny', Jornada::class);

        $grupo->load(['categoria.temporada', 'equipos']);

        $jornadas = $grupo->jornadas()
            ->with(['equipoDescanso', 'partidos.local', 'partidos.visitante'])
            ->orderBy('numero_jornada')
            ->paginate(15);

        $equipos = $grupo->equipos;

        return view('jornadas.index', compact('grupo', 'jornadas', 'equipos'));
    }

    /**
     * Formulario de creación de una jornada para el grupo.
     */
    public function create(Grupo $grupo): View
    {
        Gate::authorize('create', [Jornada::class, $grupo]);

        $grupo->load(['categoria.temporada', 'equipos']);

        $siguienteNumero = ((int) $grupo->jornadas()->max('numero_jornada')) + 1;
        $equipos = $grupo->equipos()->orderBy('nombre')->get();

        return view('jornadas.create', compact('grupo', 'siguienteNumero', 'equipos'));
    }

    /**
     * Persistir una nueva jornada en el grupo.
     */
    public function store(StoreJornadaRequest $request, Grupo $grupo): RedirectResponse
    {
        $jornada = $this->programacionService->crearJornada($grupo, $request->validated());

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', "Jornada {$jornada->numero_jornada} creada exitosamente.");
    }

    /**
     * Detalle de la jornada con sus partidos programados, descansos y validación de publicación.
     */
    public function show(Jornada $jornada): View
    {
        Gate::authorize('view', $jornada);

        $jornada->load([
            'grupo.categoria.temporada',
            'grupo.equipos' => fn ($q) => $q->orderBy('nombre'),
            'equipoDescanso',
            'aprobadoPor',
            'partidos.local',
            'partidos.visitante',
            'partidos.cancha',
            'partidos.arbitro.persona',
        ]);

        $grupo = $jornada->grupo;
        $equipos = $grupo->equipos;
        $conflictos = $jornada->partidos->isNotEmpty()
            ? $this->publicacionService->detectarConflictos($jornada)
            : [];

        return view('jornadas.show', compact('jornada', 'grupo', 'equipos', 'conflictos'));
    }

    /**
     * Formulario para editar una jornada existente.
     */
    public function edit(Jornada $jornada): View
    {
        Gate::authorize('update', $jornada);

        $jornada->load(['grupo.categoria.temporada', 'grupo.equipos', 'equipoDescanso']);
        $grupo = $jornada->grupo;
        $equipos = $grupo->equipos()->orderBy('nombre')->get();

        return view('jornadas.edit', compact('jornada', 'grupo', 'equipos'));
    }

    /**
     * Actualizar los datos de la jornada.
     */
    public function update(UpdateJornadaRequest $request, Jornada $jornada): RedirectResponse
    {
        $this->programacionService->actualizarJornada($jornada, $request->validated());

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', "Jornada {$jornada->numero_jornada} actualizada correctamente.");
    }

    /**
     * Asignar o remover el equipo en descanso de la jornada (HU-028).
     */
    public function actualizarDescanso(Request $request, Jornada $jornada): RedirectResponse
    {
        Gate::authorize('update', $jornada);

        $equipoId = $request->filled('equipo_descanso_id') ? (int) $request->input('equipo_descanso_id') : null;

        $this->programacionService->asignarDescanso($jornada, $equipoId);

        $mensaje = $equipoId !== null
            ? 'Equipo en descanso registrado exitosamente.'
            : 'Se ha removido el descanso de la jornada.';

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', $mensaje);
    }

    /**
     * Eliminar la jornada.
     */
    public function destroy(Jornada $jornada): RedirectResponse
    {
        Gate::authorize('delete', $jornada);

        $grupo = $jornada->grupo;
        $numero = $jornada->numero_jornada;

        $this->programacionService->eliminarJornada($jornada);

        return redirect()->route('grupos.jornadas.index', $grupo)
            ->with('success', "Jornada {$numero} eliminada exitosamente.");
    }

    /**
     * Enviar la programación semanal a revisión (HU-030 / SCRUM-199).
     */
    public function enviarRevision(Jornada $jornada): RedirectResponse
    {
        Gate::authorize('enviarRevision', $jornada);

        $this->publicacionService->enviarARevision($jornada, auth()->user());

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', 'La programación de la jornada se ha enviado a revisión exitosamente.');
    }

    /**
     * Aprobación de la programación semanal (HU-030 / SCRUM-199).
     */
    public function aprobar(Jornada $jornada): RedirectResponse
    {
        Gate::authorize('aprobar', $jornada);

        $this->publicacionService->aprobar($jornada, auth()->user());

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', 'La programación semanal ha sido aprobada exitosamente.');
    }

    /**
     * Publicación oficial de la programación semanal tras resolver conflictos (HU-030 / SCRUM-201).
     */
    public function publicar(Jornada $jornada): RedirectResponse
    {
        Gate::authorize('publicar', $jornada);

        $this->publicacionService->publicar($jornada, auth()->user());

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', 'La programación semanal ha sido publicada oficialmente.');
    }

    /**
     * Detección de conflictos de programación en formato JSON (HU-030 / SCRUM-200).
     */
    public function conflictos(Jornada $jornada): JsonResponse
    {
        Gate::authorize('view', $jornada);

        $conflictos = $this->publicacionService->detectarConflictos($jornada);

        return response()->json([
            'jornada_id' => $jornada->id,
            'numero_jornada' => $jornada->numero_jornada,
            'tiene_conflictos' => ! empty($conflictos),
            'conflictos' => $conflictos,
        ]);
    }
}
