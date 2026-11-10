@extends('layouts.admin')

@section('title', 'Historial de equipos - SGLD')
@section('header', 'Equipos')

@section('content')
{{-- Equipos con su número de temporadas (HU-015, RF-03.4) --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white">Historial de equipos</h2>
        <p class="text-sm text-gray-400">Un equipo es el mismo de una temporada a otra cuando conserva su club y su nombre.</p>
    </div>

    <x-table caption="Equipos registrados y las temporadas en que han participado">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3">Equipo</th>
                <th scope="col" class="px-4 py-3">Club</th>
                <th scope="col" class="px-4 py-3">Temporadas</th>
                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
            @forelse ($equipos as $equipo)
                <tr>
                    <td class="px-4 py-3 font-semibold text-white">{{ $equipo->nombre }}</td>
                    <td class="px-4 py-3">{{ $equipo->club->nombre }}</td>
                    <td class="px-4 py-3">{{ $equipo->total_temporadas }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('equipos.historial.show', $equipo->ultimo_id) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                            Ver historial<span class="sr-only"> de {{ $equipo->nombre }}</span>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-gray-400">
                        No hay equipos registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $equipos->links() }}
    </div>
</div>
@endsection
