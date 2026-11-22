<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReporteFinancieroRequest;
use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Temporada;
use App\Services\ReporteFinancieroService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Reporte financiero por periodo, club, temporada y concepto (HU-052, RF-13.3)
class ReporteFinancieroController extends Controller
{
    public function __construct(private readonly ReporteFinancieroService $reportes)
    {
    }

    public function index(ReporteFinancieroRequest $request): View
    {
        $filtros = $request->filtros();

        return view('reportes.financiero', [
            'clubes' => Club::query()->orderBy('nombre')->get(['id', 'nombre']),
            'temporadas' => Temporada::query()->orderByDesc('fecha_inicio')->get(['id', 'nombre']),
            'conceptos' => CargoFinanciero::CONCEPTOS,
            'filtros' => $filtros,
            'reporte' => $filtros !== null ? $this->reportes->generar(...$filtros) : null,
        ]);
    }

    public function csv(ReporteFinancieroRequest $request): StreamedResponse
    {
        /** @var array{desde: string, hasta: string, clubId: int, temporadaId: ?int, concepto: ?string} $filtros */
        $filtros = $request->filtros();
        $reporte = $this->reportes->generar(...$filtros);
        $encabezado = $this->encabezado($filtros);
        $pesos = fn (int $centavos): string => CargoFinanciero::formatoCentavos($centavos);

        $lineas = [
            ['Reporte financiero'],
            ['Club', $encabezado['club']->nombre],
            ['Periodo (vencimiento)', $encabezado['periodo']],
            ['Temporada', $encabezado['temporada']],
            ['Concepto', $encabezado['concepto']],
            [],
            ['Concepto', 'Cargos', 'Cargado', 'Cobrado', 'Saldo'],
        ];

        foreach ($reporte['filas'] as $fila) {
            $lineas[] = [$fila['concepto'], (string) $fila['cargos'], $pesos($fila['cargado']), $pesos($fila['cobrado']), $pesos($fila['saldo'])];
        }

        $totales = $reporte['totales'];
        $lineas[] = ['Total', (string) $totales['cargos'], $pesos($totales['cargado']), $pesos($totales['cobrado']), $pesos($totales['saldo'])];

        return response()->streamDownload(function () use ($lineas): void {
            $salida = fopen('php://output', 'w');

            if ($salida === false) {
                return;
            }

            // BOM para que Excel abra bien los acentos
            fwrite($salida, "\u{FEFF}");

            foreach ($lineas as $linea) {
                fputcsv($salida, array_map(self::celdaSegura(...), $linea), ',', '"', '');
            }

            fclose($salida);
        }, $this->nombreArchivo($encabezado['club'], $filtros, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(ReporteFinancieroRequest $request): Response
    {
        /** @var array{desde: string, hasta: string, clubId: int, temporadaId: ?int, concepto: ?string} $filtros */
        $filtros = $request->filtros();

        $encabezado = $this->encabezado($filtros);

        return Pdf::loadView('reportes.financiero-pdf', [
            'encabezado' => $encabezado,
            'reporte' => $this->reportes->generar(...$filtros),
            'generado' => now('America/Mexico_City')->format('d/m/Y H:i'),
        ])->download($this->nombreArchivo($encabezado['club'], $filtros, 'pdf'));
    }

    /**
     * Datos de los filtros para mostrarlos en las descargas.
     *
     * @param  array{desde: string, hasta: string, clubId: int, temporadaId: ?int, concepto: ?string}  $filtros
     * @return array{club: Club, periodo: string, temporada: string, concepto: string}
     */
    private function encabezado(array $filtros): array
    {
        return [
            'club' => Club::query()->findOrFail($filtros['clubId']),
            'periodo' => $filtros['desde'].' a '.$filtros['hasta'],
            'temporada' => $filtros['temporadaId'] !== null ? (string) Temporada::query()->whereKey($filtros['temporadaId'])->value('nombre') : 'Todas',
            'concepto' => $filtros['concepto'] !== null ? CargoFinanciero::CONCEPTOS[$filtros['concepto']] : 'Todos',
        ];
    }

    /**
     * @param  array{desde: string, hasta: string, clubId: int, temporadaId: ?int, concepto: ?string}  $filtros
     */
    private function nombreArchivo(Club $club, array $filtros, string $extension): string
    {
        return 'reporte-financiero-'.Str::slug($club->nombre).'-'.$filtros['desde'].'-a-'.$filtros['hasta'].'.'.$extension;
    }

    // Un texto que empieza con =, +, - o @ lo ejecutaría Excel como fórmula; se antepone un apóstrofo
    private static function celdaSegura(string $valor): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valor) === 1 ? "'".$valor : $valor;
    }
}
