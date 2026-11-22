{{-- Reporte financiero para dompdf (HU-052): HTML simple con estilos en línea; sin Vite ni recursos remotos --}}
@php
    $pesos = fn (int $centavos): string => '$'.number_format($centavos / 100, 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte financiero - {{ $encabezado['club']->nombre }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .sub { color: #4b5563; margin: 0 0 14px; }
        .datos { margin-bottom: 14px; }
        .datos td { padding: 2px 10px 2px 0; }
        .datos td:first-child { font-weight: bold; }
        table.montos { width: 100%; border-collapse: collapse; }
        .montos th, .montos td { border: 1px solid #d1d5db; padding: 6px 8px; }
        .montos th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; white-space: nowrap; }
        .montos tfoot td, .montos tfoot th { font-weight: bold; background: #f9fafb; }
        .vacio { padding: 12px; border: 1px solid #d1d5db; color: #4b5563; }
        .pie { margin-top: 16px; color: #6b7280; font-size: 9px; }
    </style>
</head>
<body>
    <h1>Reporte financiero</h1>
    <p class="sub">Liga Municipal de Fútbol Amateur de Morelia · El periodo toma en cuenta la fecha de vencimiento de cada cargo.</p>

    <table class="datos">
        <tr><td>Club</td><td>{{ $encabezado['club']->nombre }}</td></tr>
        <tr><td>Periodo</td><td>{{ $encabezado['periodo'] }}</td></tr>
        <tr><td>Temporada</td><td>{{ $encabezado['temporada'] }}</td></tr>
        <tr><td>Concepto</td><td>{{ $encabezado['concepto'] }}</td></tr>
    </table>

    @if ($reporte['filas'] === [])
        <p class="vacio">No hay cargos de este club con vencimiento en ese periodo para los filtros elegidos.</p>
    @else
        <table class="montos">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="num">Cargos</th>
                    <th class="num">Cargado</th>
                    <th class="num">Cobrado</th>
                    <th class="num">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reporte['filas'] as $fila)
                    <tr>
                        <td>{{ $fila['concepto'] }}</td>
                        <td class="num">{{ $fila['cargos'] }}</td>
                        <td class="num">{{ $pesos($fila['cargado']) }}</td>
                        <td class="num">{{ $pesos($fila['cobrado']) }}</td>
                        <td class="num">{{ $pesos($fila['saldo']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <td class="num">{{ $reporte['totales']['cargos'] }}</td>
                    <td class="num">{{ $pesos($reporte['totales']['cargado']) }}</td>
                    <td class="num">{{ $pesos($reporte['totales']['cobrado']) }}</td>
                    <td class="num">{{ $pesos($reporte['totales']['saldo']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <p class="pie">Generado el {{ $generado }} (hora de Morelia).</p>
</body>
</html>
