<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJornadaRequest;
use App\Http\Requests\UpdateJornadaRequest;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Services\ProgramacionJornadaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Gestión de jornadas y descansos por grupo (HU-027, RF-06.1; HU-028, RF-06.2)
class JornadaController extends Controller
{
    public function __construct(
        private readonly ProgramacionJornadaService $programacionService
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
     * Detalle de la jornada con sus partidos programados y equipo en descanso.
     */
    public function show(Jornada $jornada): View
    {
        Gate::authorize('view', $jornada);

        $jornada->load([
            'grupo.categoria.temporada',
            'grupo.equipos' => fn ($q) => $q->orderBy('nombre'),
            'equipoDescanso',
            'partidos.local',
            'partidos.visitante',
            'partidos.cancha',
            'partidos.arbitro.persona',
        ]);

        $grupo = $jornada->grupo;
        $equipos = $grupo->equipos;

        return view('jornadas.show', compact('jornada', 'grupo', 'equipos'));
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
}
