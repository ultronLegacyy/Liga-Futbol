@extends('layouts.admin')

@section('title', 'Canchas y sedes - SGLD')
@section('header', 'Canchas y Sedes')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Catálogo de canchas</h2>
            <p class="text-sm text-gray-400">Canchas y sedes con su capacidad de partidos consecutivos.</p>
        </div>
        @can('create', App\Models\Cancha::class)
            <a href="{{ route('canchas.create') }}" class="self-start px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                + Nueva cancha
            </a>
        @endcan
    </div>

    @if (session('success'))
        <p role="status" class="mb-4 px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 text-sm">
            {{ session('success') }}
        </p>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-300">
            <caption class="sr-only">Catálogo de canchas y sedes</caption>
            <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                <tr>
                    <th scope="col" class="px-4 py-3">Nombre</th>
                    <th scope="col" class="px-4 py-3">Dirección o ubicación</th>
                    <th scope="col" class="px-4 py-3">Partidos consecutivos</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @forelse ($canchas as $cancha)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-white">{{ $cancha->nombre }}</td>
                        <td class="px-4 py-3">{{ $cancha->direccion_ubicacion ?? 'Sin dirección registrada' }}</td>
                        <td class="px-4 py-3">{{ $cancha->capacidad_partidos_consecutivos }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded bg-gray-700 text-gray-200 border border-gray-600">
                                {{ $cancha->estado }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            <a href="{{ route('canchas.show', $cancha) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                                Ver<span class="sr-only"> {{ $cancha->nombre }}</span>
                            </a>
                            @can('view', $cancha)
                                <a href="{{ route('canchas.bloqueos.index', $cancha) }}" class="text-green-400 hover:underline focus:outline-none focus:ring-2 focus:ring-green-400 rounded">
                                    Disponibilidad y Bloqueos<span class="sr-only"> de {{ $cancha->nombre }}</span>
                                </a>
                            @endcan
                            @can('update', $cancha)
                                <a href="{{ route('canchas.edit', $cancha) }}" class="text-yellow-400 hover:underline focus:outline-none focus:ring-2 focus:ring-yellow-400 rounded">
                                    Editar<span class="sr-only"> {{ $cancha->nombre }}</span>
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-400">
                            No hay canchas registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
