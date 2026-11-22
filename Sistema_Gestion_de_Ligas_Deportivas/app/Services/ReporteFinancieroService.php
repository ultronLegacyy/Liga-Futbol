<?php

namespace App\Services;

use App\Models\CargoFinanciero;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reporte financiero de HU-052 (RF-13.3): lo cargado, lo cobrado y el saldo de un club en un periodo, por concepto.
 * El periodo se mide con el vencimiento del cargo, así que cargado = cobrado + saldo (un pago nunca supera el saldo, HU-051).
 */
class ReporteFinancieroService
{
    /**
     * Una sola consulta agrupada en la base, sin importar cuántos cargos o pagos haya. Montos en centavos enteros.
     *
     * @return array{
     *     filas: list<array{concepto: string, cargos: int, cargado: int, cobrado: int, saldo: int}>,
     *     totales: array{cargos: int, cargado: int, cobrado: int, saldo: int}
     * }
     */
    public function generar(string $desde, string $hasta, int $clubId, ?int $temporadaId = null, ?string $concepto = null): array
    {
        $pagado = DB::table('pagos')
            ->select('cargo_id')
            ->selectRaw('sum(monto) as pagado')
            ->groupBy('cargo_id');

        $grupos = DB::table('cargos_financieros as c')
            ->leftJoinSub($pagado, 'p', 'p.cargo_id', '=', 'c.id')
            ->where('c.club_id', $clubId)
            ->whereBetween('c.vencimiento', [$desde, $hasta])
            ->when($temporadaId !== null, fn (Builder $consulta) => $consulta->where('c.temporada_id', $temporadaId))
            ->when($concepto !== null, fn (Builder $consulta) => $consulta->where('c.concepto', $concepto))
            ->groupBy('c.concepto')
            ->orderBy('c.concepto')
            ->select('c.concepto')
            ->selectRaw('count(*) as cargos')
            ->selectRaw('sum(c.monto) as cargado')
            ->selectRaw('sum(coalesce(p.pagado, 0)) as cobrado')
            // Por cargo y nunca negativo, igual que CargoFinanciero::centavosDeSaldo()
            ->selectRaw('sum(greatest(c.monto - coalesce(p.pagado, 0), 0)) as saldo')
            ->get();

        $filas = [];
        $totales = ['cargos' => 0, 'cargado' => 0, 'cobrado' => 0, 'saldo' => 0];

        foreach ($grupos as $grupo) {
            $fila = [
                'concepto' => CargoFinanciero::CONCEPTOS[$grupo->concepto] ?? (string) $grupo->concepto,
                'cargos' => (int) $grupo->cargos,
                'cargado' => CargoFinanciero::aCentavos($grupo->cargado),
                'cobrado' => CargoFinanciero::aCentavos($grupo->cobrado),
                'saldo' => CargoFinanciero::aCentavos($grupo->saldo),
            ];

            foreach (['cargos', 'cargado', 'cobrado', 'saldo'] as $campo) {
                $totales[$campo] += $fila[$campo];
            }

            $filas[] = $fila;
        }

        return ['filas' => $filas, 'totales' => $totales];
    }
}
