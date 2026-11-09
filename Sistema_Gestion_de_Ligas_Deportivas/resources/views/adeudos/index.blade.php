@extends('layouts.admin')

@section('title', 'Adeudos por club - SGLD')
@section('header', 'Adeudos por club')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-2.5 focus:outline-none focus:ring-2 focus:ring-green-400';
    $pesos = fn (int $centavos): string => '$'.number_format($centavos / 100, 2);
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white">Adeudos por club</h2>
        <p class="text-sm text-gray-400">Lo cargado, lo pagado y el saldo de cada club en la temporada. Un club está al corriente cuando su saldo es $0.00.</p>
    </div>

    @if ($temporadas->isEmpty())
        <x-alert type="info" message="Todavía no hay temporadas registradas." />
    @else
        <form method="GET" action="{{ route('adeudos.index') }}" class="flex flex-col sm:flex-row gap-4 sm:items-end mb-6" aria-label="Elegir temporada">
            <div class="sm:w-72">
                <x-form.field name="filtro_temporada" label="Temporada">
                    <select id="filtro_temporada" name="temporada_id" class="{{ $campo }}">
                        @foreach ($temporadas as $opcion)
                            <option value="{{ $opcion->id }}" @selected($temporada?->id === $opcion->id)>{{ $opcion->nombre }}</option>
                        @endforeach
                    </select>
                </x-form.field>
            </div>

            <button type="submit" class="self-start sm:self-auto px-4 py-2.5 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400">Consultar</button>
        </form>

        <x-table caption="Adeudos por club de la temporada {{ $temporada?->nombre }}">
            <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                <tr>
                    <th scope="col" class="px-4 py-3">Club</th>
                    <th scope="col" class="px-4 py-3 text-right">Cargado</th>
                    <th scope="col" class="px-4 py-3 text-right">Pagado</th>
                    <th scope="col" class="px-4 py-3 text-right">Saldo</th>
                    <th scope="col" class="px-4 py-3">Situación</th>
                    <th scope="col" class="px-4 py-3 text-right">Cargos</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-700">
                @forelse ($adeudos as $adeudo)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-white">{{ $adeudo['club']->nombre }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $pesos($adeudo['cargado']) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ $pesos($adeudo['pagado']) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap font-semibold text-white">{{ $pesos($adeudo['saldo']) }}</td>
                        <td class="px-4 py-3">
                            @if ($adeudo['al_corriente'])
                                <span class="text-green-400 font-semibold">Al corriente</span>
                            @else
                                <span class="text-amber-400 font-semibold">Con adeudo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('cargos.index', ['temporada_id' => $temporada?->id, 'club_id' => $adeudo['club']->id]) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                                Ver cargos<span class="sr-only"> de {{ $adeudo['club']->nombre }}</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">Esta temporada no tiene cargos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    @endif
</div>
@endsection
