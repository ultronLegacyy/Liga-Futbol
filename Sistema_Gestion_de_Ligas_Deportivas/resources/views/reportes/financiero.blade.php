@extends('layouts.admin')

@section('title', 'Reporte financiero - SGLD')
@section('header', 'Reporte financiero')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-2.5 focus:outline-none focus:ring-2 focus:ring-green-400';
    $pesos = fn (int $centavos): string => '$'.number_format($centavos / 100, 2);
    // Primero la consulta actual y luego lo que regresó con errores; un arreglo (campo[]) se ignora para no causar un 500
    $valor = function (string $nombre): string {
        $actual = request()->query($nombre);

        if (is_string($actual)) {
            return $actual;
        }

        return is_string(old($nombre)) ? old($nombre) : '';
    };
    $clubElegido = $filtros !== null ? $clubes->firstWhere('id', $filtros['clubId']) : null;
    $temporadaElegida = $filtros !== null && $filtros['temporadaId'] !== null ? $temporadas->firstWhere('id', $filtros['temporadaId']) : null;
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white">Reporte financiero</h2>
        <p class="text-sm text-gray-400">Lo cargado, lo cobrado y el saldo de un club por concepto. El periodo toma en cuenta la fecha de vencimiento de cada cargo.</p>
    </div>

    <form method="GET" action="{{ route('reportes.financiero') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6" aria-label="Filtros del reporte financiero" novalidate>
        <x-form.field name="desde" label="Desde" :required="true" :error="$errors->first('desde')">
            <input type="date" id="desde" name="desde" value="{{ $valor('desde') }}" class="{{ $campo }}" required>
        </x-form.field>

        <x-form.field name="hasta" label="Hasta" :required="true" :error="$errors->first('hasta')">
            <input type="date" id="hasta" name="hasta" value="{{ $valor('hasta') }}" class="{{ $campo }}" required>
        </x-form.field>

        <x-form.field name="club_id" label="Club" :required="true" :error="$errors->first('club_id')">
            <select id="club_id" name="club_id" class="{{ $campo }}" required>
                <option value="">Elija un club</option>
                @foreach ($clubes as $club)
                    <option value="{{ $club->id }}" @selected($valor('club_id') === (string) $club->id)>{{ $club->nombre }}</option>
                @endforeach
            </select>
        </x-form.field>

        <x-form.field name="temporada_id" label="Temporada" :error="$errors->first('temporada_id')">
            <select id="temporada_id" name="temporada_id" class="{{ $campo }}">
                <option value="">Todas</option>
                @foreach ($temporadas as $temporada)
                    <option value="{{ $temporada->id }}" @selected($valor('temporada_id') === (string) $temporada->id)>{{ $temporada->nombre }}</option>
                @endforeach
            </select>
        </x-form.field>

        <x-form.field name="concepto" label="Concepto" :error="$errors->first('concepto')">
            <select id="concepto" name="concepto" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach ($conceptos as $clave => $etiqueta)
                    <option value="{{ $clave }}" @selected($valor('concepto') === $clave)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </x-form.field>

        <div class="flex items-end">
            <button type="submit" class="w-full sm:w-auto px-4 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-400">Generar reporte</button>
        </div>
    </form>

    @if ($reporte !== null && $clubElegido !== null)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <p class="text-sm text-gray-300">
                <span class="font-semibold text-white">{{ $clubElegido->nombre }}</span>
                · del {{ $filtros['desde'] }} al {{ $filtros['hasta'] }}
                · {{ $temporadaElegida?->nombre ?? 'todas las temporadas' }}
                · {{ $filtros['concepto'] !== null ? $conceptos[$filtros['concepto']] : 'todos los conceptos' }}
            </p>

            @if ($reporte['filas'] !== [])
                <div class="flex flex-wrap gap-2">
                    @foreach (['pdf' => 'Descargar PDF', 'csv' => 'Descargar CSV'] as $formato => $texto)
                        <a href="{{ route('reportes.financiero.'.$formato, request()->only(['desde', 'hasta', 'club_id', 'temporada_id', 'concepto'])) }}" class="px-4 py-2.5 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400">
                            {{ $texto }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <x-table caption="Montos de {{ $clubElegido->nombre }} por concepto">
            <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                <tr>
                    <th scope="col" class="px-4 py-3">Concepto</th>
                    <th scope="col" class="px-4 py-3 text-right">Cargos</th>
                    <th scope="col" class="px-4 py-3 text-right">Cargado</th>
                    <th scope="col" class="px-4 py-3 text-right">Cobrado</th>
                    <th scope="col" class="px-4 py-3 text-right">Saldo</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-700">
                @forelse ($reporte['filas'] as $fila)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-white">{{ $fila['concepto'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $fila['cargos'] }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $pesos($fila['cargado']) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $pesos($fila['cobrado']) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-white">{{ $pesos($fila['saldo']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-400">No hay cargos de este club con vencimiento en ese periodo para los filtros elegidos.</td>
                    </tr>
                @endforelse
            </tbody>

            @if ($reporte['filas'] !== [])
                <tfoot class="border-t-2 border-gray-600 text-white font-bold">
                    <tr>
                        <th scope="row" class="px-4 py-3 text-left">Total</th>
                        <td class="px-4 py-3 text-right">{{ $reporte['totales']['cargos'] }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $pesos($reporte['totales']['cargado']) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $pesos($reporte['totales']['cobrado']) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $pesos($reporte['totales']['saldo']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </x-table>
    @else
        <x-alert type="info" message="Elija el periodo y el club, y después genere el reporte." />
    @endif
</div>
@endsection
