<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrearRondaLiguillaRequest;
use App\Models\FaseTorneo;
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
 * HU-060 (RF-11.6): lo mismo para la liguilla de consolación y el Campeón de Campeones; la fase llega en la ruta.
 * Consultar pide el permiso del grupo; generar y crear rondas, LlaveLiguillaPolicy (Presidente y Administrador
 * General, D-043), que además rechaza una temporada cerrada.
 */
class LiguillaController extends Controller
{
    // Prefijo del nombre de las rutas de cada fase
    private const RUTAS = [
        FaseTorneo::LIGUILLA => 'grupos.liguilla',
        FaseTorneo::CONSOLACION => 'grupos.consolacion',
        FaseTorneo::CAMPEON_DE_CAMPEONES => 'grupos.campeon-de-campeones',
    ];

    public function __construct(
        private readonly LiguillaService $liguilla,
        private readonly AvanceLiguillaService $avances,
    ) {
    }

    public function show(Request $request, Grupo $grupo, string $fase = FaseTorneo::LIGUILLA): View
    {
        Gate::authorize('view', $grupo);

        $grupo->load('categoria:id,nombre', 'temporada');
        $llaves = $this->liguilla->llaves($grupo, $fase);

        return view('liguilla.show', [
            'grupo' => $grupo,
            'fase' => $fase,
            'ruta' => self::RUTAS[$fase],
            'llaves' => $llaves,
            'rondas' => $llaves->groupBy('ronda')->map(fn ($deLaRonda, $ronda) => [
                'nombre' => $this->avances->nombreRonda($deLaRonda, (int) $ronda, $fase),
                'llaves' => $deLaRonda,
            ]),
            'proxima' => $this->avances->proximaRonda($llaves, $fase),
            'campeones' => $this->avances->campeones($llaves),
            'revision' => $llaves->isEmpty() ? $this->liguilla->revisar($grupo, $fase) : null,
            'puedeGenerar' => $request->user()?->can('create', [LlaveLiguilla::class, $grupo, $fase]) ?? false,
            'puedeCrearRonda' => $request->user()?->can('crearRonda', [LlaveLiguilla::class, $grupo, $fase]) ?? false,
        ]);
    }

    public function store(Request $request, Grupo $grupo, string $fase = FaseTorneo::LIGUILLA): RedirectResponse
    {
        Gate::authorize('create', [LlaveLiguilla::class, $grupo, $fase]);

        /** @var User $usuario */
        $usuario = $request->user();
        $llaves = $this->liguilla->generar($grupo, $usuario, $fase);

        return redirect()
            ->route(self::RUTAS[$fase], $grupo)
            ->with('success', $fase === FaseTorneo::CAMPEON_DE_CAMPEONES
                ? 'Se registró la llave de Campeón de Campeones.'
                : 'Se generaron '.$llaves->count().' llave(s) de '.FaseTorneo::EN_TEXTO[$fase].'.');
    }

    public function crearRonda(CrearRondaLiguillaRequest $request, Grupo $grupo, string $fase = FaseTorneo::LIGUILLA): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $ronda = $this->avances->crearRonda($grupo, $request->string('fecha_inicio')->value(), $request->string('fecha_fin')->value(), $usuario, $fase);

        return redirect()
            ->route(self::RUTAS[$fase], $grupo)
            ->with('success', $fase === FaseTorneo::CAMPEON_DE_CAMPEONES
                ? 'Se creó el partido de Campeón de Campeones. Queda en borrador para que el Programador de Partidos le asigne cancha, árbitro y horario.'
                : "Se creó la ronda {$ronda} de la ".FaseTorneo::EN_TEXTO[$fase].'. Sus partidos quedan en borrador para que el Programador de Partidos les asigne cancha, árbitro y horario.');
    }
}
