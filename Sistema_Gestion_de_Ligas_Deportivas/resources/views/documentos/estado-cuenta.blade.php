@extends('documentos._base', ['titulo' => 'Estado de cuenta - '.$club->nombre])

@php
    $pesos = fn (int $centavos): string => '$'.number_format($centavos / 100, 2);
    $centavos = fn ($monto): int => \App\Models\CargoFinanciero::aCentavos($monto);
@endphp

@section('documento')
    <h1>Estado de cuenta</h1>
    <p class="sub">Cargos y pagos registrados del club. El saldo de cada cargo nunca es negativo.</p>

    <table class="datos">
        <tr><td class="et">Club</td><td>{{ $club->nombre }}</td></tr>
        <tr><td class="et">Temporada</td><td>{{ $temporada?->nombre ?? 'Todas' }}</td></tr>
        <tr><td class="et">Total cargado</td><td>{{ $pesos($estado['cargado']) }}</td></tr>
        <tr><td class="et">Total pagado</td><td>{{ $pesos($estado['pagado']) }}</td></tr>
        <tr><td class="et">Saldo pendiente</td><td><b>{{ $pesos($estado['saldo']) }}</b>{{ $estado['saldo'] === 0 ? ' · Al corriente' : '' }}</td></tr>
    </table>

    <h2>Cargos</h2>
    @if ($estado['cargos']->isEmpty())
        <p class="vacio">El club no tiene cargos registrados{{ $temporada ? ' en esta temporada' : '' }}.</p>
    @else
        <table class="tabla">
            <thead>
                <tr>
                    <th>Vence</th>
                    <th>Concepto</th>
                    <th>Aplicado a</th>
                    <th>Estado</th>
                    <th class="num">Monto</th>
                    <th class="num">Pagado</th>
                    <th class="num">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($estado['cargos'] as $cargo)
                    <tr>
                        <td>{{ $cargo->vencimiento->format('d/m/Y') }}</td>
                        <td>{{ \App\Models\CargoFinanciero::CONCEPTOS[$cargo->concepto] ?? $cargo->concepto }}@if (! $temporada && $cargo->temporada) <br><small>{{ $cargo->temporada->nombre }}</small>@endif</td>
                        <td>
                            @if ($cargo->persona)
                                {{ trim($cargo->persona->nombre.' '.$cargo->persona->apellidos) }}
                            @elseif ($cargo->equipo)
                                {{ $cargo->equipo->nombre }}
                            @else
                                Club
                            @endif
                        </td>
                        <td>{{ $cargo->estadoMostrado() }}</td>
                        <td class="num">{{ $pesos($centavos($cargo->monto)) }}</td>
                        <td class="num">{{ $pesos($centavos($cargo->montoPagado())) }}</td>
                        <td class="num">{{ $pesos($cargo->centavosDeSaldo()) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">Total ({{ $estado['cargos']->count() }} {{ $estado['cargos']->count() === 1 ? 'cargo' : 'cargos' }})</td>
                    <td class="num">{{ $pesos($estado['cargado']) }}</td>
                    <td class="num">{{ $pesos($estado['pagado']) }}</td>
                    <td class="num">{{ $pesos($estado['saldo']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2>Pagos</h2>
    @if ($estado['pagos']->isEmpty())
        <p class="vacio">No hay pagos registrados.</p>
    @else
        <table class="tabla">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cargo</th>
                    <th>Método</th>
                    <th>Referencia</th>
                    <th>Validado</th>
                    <th class="num">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($estado['pagos'] as $pago)
                    @php($cargoDelPago = $estado['cargos']->firstWhere('id', $pago->cargo_id))
                    <tr>
                        <td>{{ $pago->created_at?->timezone('America/Mexico_City')->format('d/m/Y') }}</td>
                        <td>{{ $cargoDelPago ? (\App\Models\CargoFinanciero::CONCEPTOS[$cargoDelPago->concepto] ?? $cargoDelPago->concepto).' · vence '.$cargoDelPago->vencimiento->format('d/m/Y') : '—' }}</td>
                        <td>{{ $pago->metodo_pago ?? '—' }}</td>
                        <td>{{ $pago->referencia ?? '—' }}</td>
                        <td>{{ $pago->validado_por_tesorero_id ? 'Sí' : 'Pendiente' }}</td>
                        <td class="num">{{ $pesos($centavos($pago->monto)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="sello">Tesorería de la Liga. Este estado de cuenta refleja los cargos y pagos registrados en el sistema a la fecha de emisión.</div>
@endsection
