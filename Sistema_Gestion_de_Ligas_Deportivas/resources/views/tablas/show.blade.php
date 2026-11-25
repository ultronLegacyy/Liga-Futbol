@extends('layouts.admin')

@section('title', 'Tabla de posiciones de '.$grupo->nombre.' - SGLD')
@section('header', 'Tabla de posiciones')

@section('content')
{{-- HU-041: indicador calculado con los resultados oficiales (validados en HU-040) y las reglas de la temporada --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-5xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $grupo->categoria?->nombre }} · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">Temporada {{ $grupo->temporada?->nombre }}</p>
        </div>
        @if ($grupo->categoria)
            <a href="{{ route('categorias.show', $grupo->categoria) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
                Volver a la categoría
            </a>
        @endif
    </div>

    <p class="text-sm text-gray-300">
        <x-badge variant="info">Calculado</x-badge>
        Se calcula solo con resultados oficiales y se actualiza al validar cada uno.
        @if ($regla)
            Puntos según la versión {{ $regla->version }} de las reglas de la temporada:
            victoria {{ $regla->reglas['clasificacion']['puntos_victoria'] ?? '—' }},
            empate {{ $regla->reglas['clasificacion']['puntos_empate'] ?? '—' }},
            derrota {{ $regla->reglas['clasificacion']['puntos_derrota'] ?? '—' }}.
        @endif
    </p>

    @if ($tabla->isEmpty())
        <p class="text-sm text-gray-400">Todavía no hay resultados oficiales en este grupo.</p>
    @else
        <x-table caption="Posiciones del grupo">
            <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                <tr>
                    <th scope="col" class="px-3 py-3"><abbr title="Posición">Pos</abbr></th>
                    <th scope="col" class="px-3 py-3">Equipo</th>
                    <th scope="col" class="px-3 py-3 text-right"><abbr title="Partidos jugados">PJ</abbr></th>
                    <th scope="col" class="px-3 py-3 text-right"><abbr title="Partidos ganados">PG</abbr></th>
                    <th scope="col" class="px-3 py-3 text-right"><abbr title="Partidos empatados">PE</abbr></th>
                    <th scope="col" class="px-3 py-3 text-right"><abbr title="Partidos perdidos">PP</abbr></th>
                    <th scope="col" class="px-3 py-3 text-right"><abbr title="Goles a favor">GF</abbr></th>
                    <th scope="col" class="px-3 py-3 text-right"><abbr title="Goles en contra">GC</abbr></th>
                    <th scope="col" class="px-3 py-3 text-right"><abbr title="Diferencia de goles">DG</abbr></th>
                    <th scope="col" class="px-3 py-3 text-right"><abbr title="Puntos">Pts</abbr></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @foreach ($tabla as $renglon)
                    <tr data-equipo="{{ $renglon->equipo_id }}">
                        <td class="px-3 py-3 font-semibold text-white">{{ $renglon->posicion }}</td>
                        <td class="px-3 py-3">
                            <span class="text-white">{{ $renglon->equipo?->nombre }}</span>
                            <span class="block text-xs text-gray-400">{{ $renglon->equipo?->club?->nombre }}</span>
                        </td>
                        <td class="px-3 py-3 text-right">{{ $renglon->pj }}</td>
                        <td class="px-3 py-3 text-right">{{ $renglon->pg }}</td>
                        <td class="px-3 py-3 text-right">{{ $renglon->pe }}</td>
                        <td class="px-3 py-3 text-right">{{ $renglon->pp }}</td>
                        <td class="px-3 py-3 text-right">{{ $renglon->gf }}</td>
                        <td class="px-3 py-3 text-right">{{ $renglon->gc }}</td>
                        <td class="px-3 py-3 text-right">{{ $renglon->dg }}</td>
                        <td class="px-3 py-3 text-right font-semibold text-white">{{ $renglon->puntos }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif
</div>
@endsection
