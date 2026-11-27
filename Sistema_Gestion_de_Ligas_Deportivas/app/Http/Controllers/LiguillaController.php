<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrearRondaLiguillaRequest;
use App\Models\Grupo;
use App\Models\LlaveLiguilla;
use App\Models\User;
use App\Services\AvanceLiguillaService;
use App\Services\LiguillaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * HU-055 (RF-11.1): clasificación a la liguilla y llaves de un grupo.
 * HU-056 (RF-11.2): rondas, avances, eliminados, campeón y subcampeón.
 * Consultar pide el permiso del grupo; generar y crear rondas, LlaveLiguillaPolicy (Presidente y Administrador
 * General, D-043), que además rechaza una temporada cerrada.
 */
class LiguillaController extends Controller
{
    public function __construct(
        private readonly LiguillaService $liguilla,
        private readonly AvanceLiguillaService $avances,
    ) {
    }

    public function show(Request $request, Grupo $grupo): View
    {
        Gate::authorize('view', $grupo);

        $grupo->load('categoria:id,nombre', 'temporada');
        $llaves = $this->liguilla->llaves($grupo);

        return view('liguilla.show', [
            'grupo' => $grupo,
            'llaves' => $llaves,
            'rondas' => $llaves->groupBy('ronda')->map(fn ($deLaRonda, $ronda) => [
                'nombre' => $this->avances->nombreRonda($deLaRonda, (int) $ronda),
                'llaves' => $deLaRonda,
            ]),
            'proxima' => $this->avances->proximaRonda($llaves),
            'campeones' => $this->avances->campeones($llaves),
            'revision' => $llaves->isEmpty() ? $this->liguilla->revisar($grupo) : null,
            'puedeGenerar' => $request->user()?->can('create', [LlaveLiguilla::class, $grupo]) ?? false,
            'puedeCrearRonda' => $request->user()?->can('crearRonda', [LlaveLiguilla::class, $grupo]) ?? false,
        ]);
    }

    public function store(Request $request, Grupo $grupo): RedirectResponse
    {
        Gate::authorize('create', [LlaveLiguilla::class, $grupo]);

        /** @var User $usuario */
        $usuario = $request->user();
        $llaves = $this->liguilla->generar($grupo, $usuario);

        return redirect()
            ->route('grupos.liguilla', $grupo)
            ->with('success', 'Se generaron '.$llaves->count().' llave(s) de liguilla.');
    }

    public function crearRonda(CrearRondaLiguillaRequest $request, Grupo $grupo): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $ronda = $this->avances->crearRonda($grupo, $request->string('fecha_inicio')->value(), $request->string('fecha_fin')->value(), $usuario);

        return redirect()
            ->route('grupos.liguilla', $grupo)
            ->with('success', "Se creó la ronda {$ronda} de la liguilla. Sus partidos quedan en borrador para que el Programador de Partidos les asigne cancha, árbitro y horario.");
    }
}
