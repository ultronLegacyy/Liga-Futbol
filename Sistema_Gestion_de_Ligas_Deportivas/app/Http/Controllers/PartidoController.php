<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartidoRequest;
use App\Http\Requests\UpdatePartidoRequest;
use App\Models\Arbitro;
use App\Models\Cancha;
use App\Models\Jornada;
use App\Models\Partido;
use App\Services\ProgramacionJornadaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Alta manual y reprogramación de partidos en jornadas (HU-027, RF-06.1)
class PartidoController extends Controller
{
    public function __construct(
        private readonly ProgramacionJornadaService $programacionService
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
        $this->programacionService->actualizarPartido($partido, $request->validated());

        return redirect()->route('jornadas.show', $partido->jornada)
            ->with('success', 'Partido actualizado y reprogramado correctamente.');
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
