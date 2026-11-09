@extends('layouts.admin')

@section('title', 'Historial de '.$equipo->nombre.' - SGLD')
@section('header', 'Equipos')

@section('content')
{{-- CA1 de HU-015: temporadas, categorías y grupos en los que participó el equipo --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-4xl">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $equipo->nombre }}</h2>
            <p class="text-sm text-gray-400">Club {{ $equipo->club->nombre }} · historial por temporada, de la más reciente a la más antigua.</p>
        </div>
        <a href="{{ route('equipos.historial.index') }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver al historial de equipos
        </a>
    </div>

    <x-table caption="Participación del equipo en cada temporada">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3">Temporada</th>
                <th scope="col" class="px-4 py-3">Periodo</th>
                <th scope="col" class="px-4 py-3">Categoría</th>
                <th scope="col" class="px-4 py-3">Grupo</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
            @forelse ($participaciones as $participacion)
                {{-- La temporada sale del grupo, que siempre la tiene --}}
                @php($temporada = $participacion->grupo->temporada)
                <tr>
                    <td class="px-4 py-3 font-semibold text-white">
                        {{ $temporada->nombre }}
                        @if ($temporada->estado === 'cerrada')
                            <span class="ml-2 px-2 py-0.5 text-xs rounded bg-gray-700 text-gray-300 border border-gray-600">Cerrada</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        {{ \Illuminate\Support\Carbon::parse($temporada->fecha_inicio)->format('d/m/Y') }}
                        –
                        {{ \Illuminate\Support\Carbon::parse($temporada->fecha_fin)->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3">
                        {{ $participacion->grupo->categoria->nombre }}
                        @if (filled($participacion->grupo->categoria->rama))
                            <span class="text-gray-400">({{ $participacion->grupo->categoria->rama }})</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $participacion->grupo->nombre }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-gray-400">
                        Este equipo aún no tiene participaciones registradas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-table>
</div>
@endsection
