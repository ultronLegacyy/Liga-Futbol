@extends('layouts.admin')

@section('title', 'Goleo de '.$temporada->nombre.' - SGLD')
@section('header', $cerrada ? 'Campeones de goleo' : 'Líderes de goleo')

@section('content')
{{-- HU-060 CA3: campeón de goleo de cada categoría; solo la fase regular, sin autogoles; empate por la tabla y, si sigue, compartido --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-4xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Temporada {{ $temporada->nombre }}</h2>
            <p class="text-sm text-gray-400">Goles de los resultados oficiales de la fase regular, sin autogoles. La liguilla no cuenta.</p>
        </div>
        <a href="{{ route('temporadas.show', $temporada) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver a la temporada
        </a>
    </div>

    @if (! $cerrada)
        <p class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
            La temporada sigue abierta: se muestra el líder de goleo provisional. El campeón de goleo se define al cierre de la temporada.
        </p>
    @endif

    <p class="text-sm text-gray-300">Si dos jugadores empatan en goles, gana el que juega en el equipo mejor ubicado en la tabla general de su grupo; si siguen empatados, el título se comparte.</p>

    @if ($campeones === [])
        <p class="text-sm text-gray-400">La temporada no tiene grupos registrados.</p>
    @else
        <x-table :caption="$cerrada ? 'Campeón de goleo por categoría' : 'Líder de goleo provisional por categoría'">
            <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                <tr>
                    <th scope="col" class="px-4 py-3">Categoría</th>
                    <th scope="col" class="px-4 py-3">{{ $cerrada ? 'Campeón de goleo' : 'Líder de goleo' }}</th>
                    <th scope="col" class="px-4 py-3">Equipo</th>
                    <th scope="col" class="px-4 py-3 text-right">Goles</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @foreach ($campeones as $fila)
                    @forelse ($fila['jugadores'] as $jugador)
                        <tr data-campeon-goleo="{{ $jugador->id }}">
                            <td class="px-4 py-3 text-white">{{ $fila['categoria']->nombre }}</td>
                            <td class="px-4 py-3 text-white">
                                {{ $jugador->persona?->nombre }} {{ $jugador->persona?->apellidos }}
                                @if (count($fila['jugadores']) > 1)
                                    <span class="text-sm text-yellow-200">(compartido)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $jugador->equipo?->nombre }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-white">{{ $fila['goles'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-3 text-white">{{ $fila['categoria']->nombre }}</td>
                            <td class="px-4 py-3 text-gray-400" colspan="3">Sin goles en resultados oficiales de la fase regular.</td>
                        </tr>
                    @endforelse
                @endforeach
            </tbody>
        </x-table>
    @endif
</div>
@endsection
