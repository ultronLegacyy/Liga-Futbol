@extends('layouts.admin')

@section('title', 'Árbitros - SGLD')
@section('header', 'Árbitros')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Padrón de árbitros</h2>
            <p class="text-sm text-gray-400">Árbitros colegiados registrados como personas con rol de árbitro.</p>
        </div>

        <div class="flex flex-wrap gap-3 self-start">
            @can('asignar', App\Models\Arbitro::class)
                <a href="{{ route('arbitros.asignaciones') }}" class="px-4 py-2 text-sm text-gray-200 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                    Asignar a partidos
                </a>
            @endcan
            @can('create', App\Models\Arbitro::class)
                <a href="{{ route('arbitros.create') }}" class="px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                    + Registrar árbitro
                </a>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <x-table caption="Padrón de árbitros">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3">Nombre</th>
                <th scope="col" class="px-4 py-3">Colegio</th>
                <th scope="col" class="px-4 py-3">Tarifa por partido</th>
                <th scope="col" class="px-4 py-3">Disponibilidad</th>
                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
            @forelse ($arbitros as $arbitro)
                <tr>
                    <td class="px-4 py-3 font-semibold text-white">{{ $arbitro->persona->apellidos }}, {{ $arbitro->persona->nombre }}</td>
                    <td class="px-4 py-3">{{ $arbitro->colegio_pertenencia ?? 'Sin colegio registrado' }}</td>
                    <td class="px-4 py-3">${{ number_format((float) $arbitro->tarifa_por_partido, 2) }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded bg-gray-700 text-gray-200 border border-gray-600">{{ $arbitro->estado }}</span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @can('update', $arbitro)
                            <a href="{{ route('arbitros.edit', $arbitro) }}" class="text-yellow-400 hover:underline focus:outline-none focus:ring-2 focus:ring-yellow-400 rounded">
                                Editar<span class="sr-only"> {{ $arbitro->persona->nombre }} {{ $arbitro->persona->apellidos }}</span>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">No hay árbitros en el padrón.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>
</div>
@endsection
