<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoPartidoRequest;
use App\Http\Requests\ReprogramarPartidoRequest;
use App\Http\Requests\StorePartidoRequest;
use App\Http\Requests\UpdatePartidoRequest;
use App\Models\Arbitro;
use App\Models\Cancha;
use App\Models\Jornada;
use App\Models\Partido;
use App\Services\GestionEstadoPartidoService;
use App\Services\ProgramacionJornadaService;
use App\Services\ReprogramacionPartidoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Alta manual, gestión de estados, reprogramación e historial de partidos (HU-027, HU-029, HU-031, RF-06.1, RF-06.3, RF-06.4, RF-06.6)
class PartidoController extends Controller
{
    public function __construct(
        private readonly ProgramacionJornadaService $programacionService,
        private readonly ReprogramacionPartidoService $reprogramacionService,
        private readonly GestionEstadoPartidoService $gestionEstadoService
    ) {
    }

    /**
     * Formulario para programar manualmente un partido en la jornada.
     */
    public function create(Jornada $jornada): View
    {
        Gate::authorize('create', [Partido::class, $jornada]);

        $jornada->load(['grupo.categoria.temporada', 'grupo.equipos', 'equipoDescanso']);
        $temporada = $jornada->grupo->temporada;

        $equipos = $jornada->grupo->equipos()->orderBy('nombre')->get();
        $canchas = Cancha::query()->where('estado', 'Disponible')->orderBy('nombre')->get();

        $arbitros = Arbitro::query()
            ->where('estado', 'Activo')
            ->with('persona:id,nombre,apellidos')
            ->get()
            ->filter(fn (Arbitro $a): bool => $temporada !== null && $a->disponibleEn($temporada))
            ->values();

        $grupo = $jornada->grupo;

        return view('partidos.create', compact('jornada', 'grupo', 'equipos', 'canchas', 'arbitros'));
    }

    /**
     * Guardar el nuevo partido programado.
     */
    public function store(StorePartidoRequest $request, Jornada $jornada): RedirectResponse
    {
        $this->programacionService->programarPartido($jornada, $request->validated());

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', 'Partido programado exitosamente.');
    }

    /**
     * Consulta del partido programado.
     */
    public function show(Partido $partido): View
    {
        Gate::authorize('view', $partido);

        $partido->load([
            'jornada.grupo.categoria.temporada',
            'local',
            'visitante',
            'cancha',
            'arbitro.persona',
        ]);

        return view('partidos.show', compact('partido'));
    }

    /**
     * Formulario para editar o reprogramar un partido existente.
     */
    public function edit(Partido $partido): View
    {
        Gate::authorize('update', $partido);

        $jornada = $partido->jornada;
        $jornada->load(['grupo.categoria.temporada', 'grupo.equipos', 'equipoDescanso']);
        $temporada = $jornada->grupo->temporada;

        $equipos = $jornada->grupo->equipos()->orderBy('nombre')->get();
        $canchas = Cancha::query()->where('estado', 'Disponible')->orderBy('nombre')->get();

        $arbitros = Arbitro::query()
            ->where('estado', 'Activo')
            ->with('persona:id,nombre,apellidos')
            ->get()
            ->filter(fn (Arbitro $a): bool => $temporada !== null && $a->disponibleEn($temporada))
            ->values();

        $grupo = $jornada->grupo;

        return view('partidos.edit', compact('partido', 'jornada', 'grupo', 'equipos', 'canchas', 'arbitros'));
    }

    /**
     * Actualizar o reprogramar los datos del partido.
     */
    public function update(UpdatePartidoRequest $request, Partido $partido): RedirectResponse
    {
        $this->programacionService->actualizarPartido($partido, $request->validated(), $request->user());

        return redirect()->route('jornadas.show', $partido->jornada)
            ->with('success', 'Partido actualizado y reprogramado correctamente.');
    }

    /**
     * Formulario para reprogramar fecha, hora, cancha o árbitro con motivo obligatorio (HU-029).
     */
    public function formularioReprogramar(Partido $partido): View
    {
        Gate::authorize('reprogramar', $partido);

        $jornada = $partido->jornada;
        $jornada->load(['grupo.categoria.temporada', 'grupo.equipos']);
        $temporada = $jornada->grupo->temporada;

        $canchas = Cancha::query()->where('estado', 'Disponible')->orderBy('nombre')->get();

        $arbitros = Arbitro::query()
            ->where('estado', 'Activo')
            ->with('persona:id,nombre,apellidos')
            ->get()
            ->filter(fn (Arbitro $a): bool => $temporada !== null && $a->disponibleEn($temporada))
            ->values();

        return view('partidos.reprogramar', compact('partido', 'jornada', 'canchas', 'arbitros'));
    }

    /**
     * Procesar la reprogramación del partido con validación de disponibilidad y registro de motivo (HU-029).
     */
    public function reprogramar(ReprogramarPartidoRequest $request, Partido $partido): RedirectResponse
    {
        Gate::authorize('reprogramar', $partido);

        $this->reprogramacionService->reprogramar($partido, $request->validated(), $request->user());

        return redirect()->route('jornadas.show', $partido->jornada_id)
            ->with('success', 'Partido reprogramado exitosamente.');
    }

    /**
     * Transicionar el estado del partido dentro del flujo oficial (HU-029).
     */
    public function cambiarEstado(CambiarEstadoPartidoRequest $request, Partido $partido): RedirectResponse
    {
        Gate::authorize('cambiarEstado', $partido);

        $validated = $request->validated();
        $nuevoEstado = (string) ($validated['nuevo_estado'] ?? $validated['estado'] ?? '');
        $this->gestionEstadoService->cambiarEstado(
            $partido,
            $nuevoEstado,
            $validated['motivo'] ?? null,
            $request->user()
        );

        return redirect()->route('jornadas.show', $partido->jornada_id)
            ->with('success', "Estado del partido actualizado a '{$nuevoEstado}' correctamente.");
    }

    /**
     * Bitácora e historial de reprogramaciones del partido (HU-029, HU-031).
     */
    public function historial(Partido $partido): View
    {
        Gate::authorize('view', $partido);

        $partido->load([
            'local',
            'visitante',
            'jornada.grupo.categoria.temporada',
            'reprogramaciones' => fn ($q) => $q->with(['usuario', 'canchaAnterior', 'canchaNueva'])->latest(),
        ]);

        return view('partidos.historial', compact('partido'));
    }

    /**
     * Eliminar un partido programado.
     */
    public function destroy(Partido $partido): RedirectResponse
    {
        Gate::authorize('delete', $partido);

        $jornada = $partido->jornada;
        $this->programacionService->eliminarPartido($partido);

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', 'Partido eliminado de la programación.');
    }
}
