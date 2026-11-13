@extends('layouts.admin')

@section('title', 'Estadísticas de '.$grupo->nombre.' - SGLD')
@section('header', 'Estadísticas')

@section('content')
{{-- HU-043: goleo, tarjetas, sanciones y tabla por jornada. RF-09.4: se distingue el dato oficial del indicador calculado --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-5xl space-y-8">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $grupo->categoria?->nombre }} · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">Temporada {{ $grupo->temporada?->nombre }}. Solo cuentan los resultados oficiales.</p>
        </div>
        <a href="{{ route('grupos.tabla', $grupo) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Ver tabla de posiciones
        </a>
    </div>

    <p class="text-sm text-gray-300 flex flex-wrap gap-x-4 gap-y-2" aria-label="Simbología">
        <span><x-badge variant="success">Oficial</x-badge> resultado validado o resolución del Tribunal de Penas.</span>
        <span><x-badge variant="info">Calculado</x-badge> indicador que el sistema obtiene de los datos oficiales.</span>
    </p>

    <section aria-labelledby="titulo-resultados" class="space-y-3">
        <h3 id="titulo-resultados" class="text-lg font-semibold text-white">Resultados oficiales <x-badge variant="success">Oficial</x-badge></h3>
        @if ($resultados->isEmpty())
            <p class="text-sm text-gray-400">Todavía no hay resultados oficiales en este grupo.</p>
        @else
            <x-table caption="Resultados validados">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Jornada</th>
                        <th scope="col" class="px-4 py-3">Partido</th>
                        <th scope="col" class="px-4 py-3">Marcador</th>
                        <th scope="col" class="px-4 py-3">Validó</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($resultados as $cedula)
                        <tr>
                            <td class="px-4 py-3">{{ $cedula->partido?->jornada?->numero_jornada }}</td>
                            <td class="px-4 py-3">{{ $cedula->partido?->local?->nombre }} vs {{ $cedula->partido?->visitante?->nombre }}</td>
                            <td class="px-4 py-3 font-mono text-white">{{ $cedula->goles_local }} - {{ $cedula->goles_visitante }}</td>
                            <td class="px-4 py-3">{{ $cedula->validadaPor?->name ?? 'Sin dato' }} · {{ $cedula->validada_en?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </section>

    <section aria-labelledby="titulo-goleo" class="space-y-3">
        <h3 id="titulo-goleo" class="text-lg font-semibold text-white">Goleo <x-badge variant="info">Calculado</x-badge></h3>
        @if ($goleadores->isEmpty())
            <p class="text-sm text-gray-400">Sin goles registrados en resultados oficiales.</p>
        @else
            <x-table caption="Goleadores (no cuenta autogoles)">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Jugador</th>
                        <th scope="col" class="px-4 py-3">Equipo</th>
                        <th scope="col" class="px-4 py-3 text-right">Goles</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($goleadores as $fila)
                        <tr data-goleador="{{ $fila['jugador']->id }}">
                            <td class="px-4 py-3 text-white">{{ $fila['jugador']->persona?->nombre }} {{ $fila['jugador']->persona?->apellidos }}</td>
                            <td class="px-4 py-3">{{ $fila['jugador']->equipo?->nombre }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-white">{{ $fila['goles'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </section>

    <section aria-labelledby="titulo-tarjetas" class="space-y-3">
        <h3 id="titulo-tarjetas" class="text-lg font-semibold text-white">Tarjetas <x-badge variant="info">Calculado</x-badge></h3>
        @if ($tarjetas->isEmpty())
            <p class="text-sm text-gray-400">Sin tarjetas registradas en resultados oficiales.</p>
        @else
            <x-table caption="Tarjetas acumuladas por jugador">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Jugador</th>
                        <th scope="col" class="px-4 py-3">Equipo</th>
                        <th scope="col" class="px-4 py-3 text-right">Amarillas</th>
                        <th scope="col" class="px-4 py-3 text-right">Rojas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($tarjetas as $fila)
                        <tr data-tarjetas="{{ $fila['jugador']->id }}">
                            <td class="px-4 py-3 text-white">{{ $fila['jugador']->persona?->nombre }} {{ $fila['jugador']->persona?->apellidos }}</td>
                            <td class="px-4 py-3">{{ $fila['jugador']->equipo?->nombre }}</td>
                            <td class="px-4 py-3 text-right">{{ $fila['amarillas'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $fila['rojas'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </section>

    <section aria-labelledby="titulo-sanciones" class="space-y-3">
        <h3 id="titulo-sanciones" class="text-lg font-semibold text-white">Sanciones <x-badge variant="success">Oficial</x-badge></h3>
        @if ($sanciones->isEmpty())
            <p class="text-sm text-gray-400">Sin sanciones del Tribunal de Penas en partidos de este grupo.</p>
        @else
            <x-table caption="Sanciones por partidos del grupo">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Persona</th>
                        <th scope="col" class="px-4 py-3">Jornada</th>
                        <th scope="col" class="px-4 py-3">Sanción</th>
                        <th scope="col" class="px-4 py-3 text-right">Partidos (cumplidos / total)</th>
                        <th scope="col" class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($sanciones as $sancion)
                        <tr>
                            <td class="px-4 py-3 text-white">{{ $sancion->nombre }} {{ $sancion->apellidos }}</td>
                            <td class="px-4 py-3">{{ $sancion->numero_jornada }}</td>
                            <td class="px-4 py-3">{{ $sancion->descripcion_sancion ?? 'Sin descripción' }}</td>
                            <td class="px-4 py-3 text-right">{{ $sancion->partidos_cumplidos }} / {{ $sancion->partidos_suspendidos }}</td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$sancion->estado === 'Vigente' ? 'danger' : 'gray'">{{ $sancion->estado }}</x-badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </section>

    <section aria-labelledby="titulo-historico" class="space-y-3">
        <h3 id="titulo-historico" class="text-lg font-semibold text-white">Tabla por jornada <x-badge variant="info">Calculado</x-badge></h3>
        <p class="text-sm text-gray-400">Copia de la tabla guardada al cerrar cada jornada, cuando todos sus partidos y los de las jornadas anteriores tienen resultado oficial.</p>

        @if ($jornadas->isEmpty())
            <p class="text-sm text-gray-400">Todavía no se cierra ninguna jornada.</p>
        @else
            <form method="GET" action="{{ route('grupos.estadisticas', $grupo) }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="jornada" class="block mb-1 text-sm font-medium text-gray-200">Jornada</label>
                    <select id="jornada" name="jornada" class="rounded-lg bg-gray-900 border border-gray-600 text-white p-2 focus:outline-none focus:ring-2 focus:ring-green-400">
                        @foreach ($jornadas as $opcion)
                            <option value="{{ $opcion->id }}" @selected($jornada?->id === $opcion->id)>Jornada {{ $opcion->numero_jornada }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400">
                    Ver
                </button>
            </form>

            <x-table :caption="'Tabla al cierre de la jornada '.$jornada?->numero_jornada">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-3 py-3">Pos</th>
                        <th scope="col" class="px-3 py-3">Equipo</th>
                        <th scope="col" class="px-3 py-3 text-right">PJ</th>
                        <th scope="col" class="px-3 py-3 text-right">PG</th>
                        <th scope="col" class="px-3 py-3 text-right">PE</th>
                        <th scope="col" class="px-3 py-3 text-right">PP</th>
                        <th scope="col" class="px-3 py-3 text-right">GF</th>
                        <th scope="col" class="px-3 py-3 text-right">GC</th>
                        <th scope="col" class="px-3 py-3 text-right">DG</th>
                        <th scope="col" class="px-3 py-3 text-right">Pts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($historico as $renglon)
                        <tr>
                            <td class="px-3 py-3 font-semibold text-white">{{ $renglon->posicion }}</td>
                            <td class="px-3 py-3 text-white">{{ $renglon->equipo?->nombre }}</td>
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
    </section>
</div>
@endsection
