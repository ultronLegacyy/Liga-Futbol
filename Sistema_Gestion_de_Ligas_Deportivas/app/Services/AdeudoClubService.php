<?php

namespace App\Services;

use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Temporada;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

// Adeudos por club y temporada (HU-051, RF-13.2); lo usan la pantalla de adeudos y los recordatorios de pago (HU-065)
class AdeudoClubService
{
    /**
     * Totales en centavos enteros, con las mismas reglas del saldo de cada cargo.
     *
     * @return list<array{club: Club, cargado: int, pagado: int, saldo: int, al_corriente: bool}>
     */
    public function adeudosPorClub(Temporada $temporada): array
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

    /**
     * Cargos sin pagar, con saldo, que vencen a más tardar en esa fecha (incluidos los ya vencidos), con el mismo saldo
     * de la pantalla. Para los recordatorios de pago (HU-065).
     *
     * @return Collection<int, CargoFinanciero>
     */
    public function cargosConSaldoQueVencenHasta(CarbonInterface $limite): Collection
    {
        return CargoFinanciero::query()
            ->where('estado', '!=', 'Pagado')
            ->whereDate('vencimiento', '<=', $limite->toDateString())
            ->withSum('pagos', 'monto')
            ->orderBy('vencimiento')->orderBy('id')
            ->get()
            ->filter(fn (CargoFinanciero $cargo): bool => $cargo->centavosDeSaldo() > 0)
            ->values();
    }
}
