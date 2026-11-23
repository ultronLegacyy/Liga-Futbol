<?php

namespace App\Http\Controllers;

use App\Models\CargoFinanciero;
use App\Models\Temporada;
use App\Services\AdeudoClubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Adeudos por club y temporada, para saber quién está al corriente (HU-051, RF-13.2)
class AdeudoClubController extends Controller
{
    public function __construct(private readonly AdeudoClubService $adeudos)
    {
    }

    /**
     * Por temporada: lo cargado, lo pagado y el saldo de cada club con cargos; al corriente si el saldo es 0.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CargoFinanciero::class);

        $temporadas = Temporada::query()->orderByDesc('fecha_inicio')->get(['id', 'nombre']);
        // Sin temporada elegida (o con una que no existe) se muestra la más reciente
        $temporada = $temporadas->firstWhere('id', $request->integer('temporada_id')) ?? $temporadas->first();

        return view('adeudos.index', [
            'temporadas' => $temporadas,
            'temporada' => $temporada,
            'adeudos' => $temporada ? $this->adeudos->adeudosPorClub($temporada) : [],
        ]);
    }
}
