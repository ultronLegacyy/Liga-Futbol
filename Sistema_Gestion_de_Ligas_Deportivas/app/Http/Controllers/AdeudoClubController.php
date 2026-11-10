<?php

namespace App\Http\Controllers;

use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Temporada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Adeudos por club y temporada, para saber quién está al corriente (HU-051, RF-13.2)
class AdeudoClubController extends Controller
{
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
            'adeudos' => $temporada ? $this->adeudosPorClub($temporada) : [],
        ]);
    }

    /**
     * Totales en centavos enteros, con las mismas reglas del saldo de cada cargo.
     *
     * @return list<array{club: Club, cargado: int, pagado: int, saldo: int, al_corriente: bool}>
     */
    private function adeudosPorClub(Temporada $temporada): array
    {
        $cargos = CargoFinanciero::query()
            ->where('temporada_id', $temporada->id)
            ->withSum('pagos', 'monto')
            ->get();

        $clubes = Club::query()
            ->whereIn('id', $cargos->pluck('club_id')->unique())
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $adeudos = [];

        foreach ($clubes as $club) {
            $deClub = $cargos->where('club_id', $club->id);
            $cargado = (int) $deClub->sum(fn (CargoFinanciero $cargo): int => CargoFinanciero::aCentavos($cargo->monto));
            $saldo = (int) $deClub->sum(fn (CargoFinanciero $cargo): int => $cargo->centavosDeSaldo());

            $adeudos[] = [
                'club' => $club,
                'cargado' => $cargado,
                'pagado' => (int) $deClub->sum(fn (CargoFinanciero $cargo): int => CargoFinanciero::aCentavos($cargo->montoPagado())),
                'saldo' => $saldo,
                'al_corriente' => $saldo === 0,
            ];
        }

        return $adeudos;
    }
}
