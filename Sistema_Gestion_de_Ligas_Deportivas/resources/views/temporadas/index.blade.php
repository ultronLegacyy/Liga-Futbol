@extends('layouts.admin')

@section('title', 'Gestión de Temporadas - SGLD')
@section('header', 'Temporadas Deportivas')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Listado de Temporadas</h2>
            <p class="text-sm text-gray-400">Consulta y administra las temporadas registradas en la liga.</p>
        </div>
        <a href="{{ route('temporadas.create') }}" class="px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 transition">
            + Nueva Temporada
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-300">
            <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Fecha Inicio</th>
                    <th class="px-4 py-3">Fecha Fin</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @forelse($temporadas ?? [] as $temporada)
                <tr class="hover:bg-gray-750">
                    <td class="px-4 py-3 font-mono text-xs">{{ $temporada->id }}</td>
                    <td class="px-4 py-3 font-semibold text-white">{{ $temporada->nombre }}</td>
                    <td class="px-4 py-3">{{ $temporada->fecha_inicio }}</td>
                    <td class="px-4 py-3">{{ $temporada->fecha_fin }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded bg-green-500/20 text-green-400 border border-green-500/30">
                            {{ $temporada->estatus ?? 'Activa' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right space-x-2">
                        <a href="{{ route('temporadas.show', $temporada) }}" class="text-blue-400 hover:underline">Ver</a>
                        <a href="{{ route('temporadas.edit', $temporada) }}" class="text-yellow-400 hover:underline">Editar</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                        No hay temporadas registradas actualmente.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection