@extends('layouts.admin')

@section('title', 'Gestión de Temporadas - SGLD')
@section('header', 'Temporadas Deportivas')

@section('content')
<div class="rounded-xl border border-gray-700 bg-gray-800 p-4 sm:p-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-white">Listado de temporadas</h2>
            <p class="mt-1 text-sm text-gray-400">
                Consulta y administra las temporadas registradas en la liga.
            </p>
        </div>

        <a href="{{ route('temporadas.create') }}"
           class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 focus:ring-offset-gray-800">
            <span aria-hidden="true" class="mr-2">+</span>
            Nueva temporada
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-700">
        <table class="w-full text-left text-sm text-gray-300">
            <caption class="sr-only">Temporadas registradas, fechas, estado y acciones disponibles.</caption>
            <thead class="bg-gray-700/70 text-xs uppercase text-gray-200">
                <tr>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">ID</th>
                    <th scope="col" class="min-w-[10rem] px-4 py-3">Nombre</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">Fecha de inicio</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3">Fecha de fin</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="whitespace-nowrap px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-700">
                @forelse($temporadas ?? [] as $temporada)
                    <tr class="transition hover:bg-gray-700/40">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">
                            {{ $temporada->id }}
                        </td>
                        <td class="px-4 py-3 font-semibold text-white">
                            {{ $temporada->nombre }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            {{ $temporada->fecha_inicio }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            {{ $temporada->fecha_fin }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="inline-flex rounded border border-green-500/30 bg-green-500/20 px-2 py-1 text-xs text-green-300">
                                {{ $temporada->estado ?? 'Activa' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-x-4 gap-y-2">
                                <a href="{{ route('temporadas.show', $temporada) }}"
                                   class="rounded text-blue-300 underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 focus:ring-offset-gray-800"
                                   aria-label="Ver temporada {{ $temporada->nombre }}">
                                    Ver
                                </a>
                                <a href="{{ route('temporadas.edit', $temporada) }}"
                                   class="rounded text-yellow-300 underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-offset-2 focus:ring-offset-gray-800"
                                   aria-label="Editar temporada {{ $temporada->nombre }}">
                                    Editar
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                            No hay temporadas registradas actualmente.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-gray-500">
        En pantallas pequeñas, desliza la tabla horizontalmente para consultar todas las columnas.
    </p>
</div>
@endsection
