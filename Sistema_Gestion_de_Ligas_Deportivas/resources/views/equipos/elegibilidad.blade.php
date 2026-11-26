@extends('layouts.admin')

@section('title', 'Elegibilidad de '.$equipo->nombre.' - SGLD')
@section('header', 'Equipos')

@section('content')
{{-- HU-019: quién puede jugar y, si no, el motivo y la regla. «No verificado» nunca se habilita (RNF-43) --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-5xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Elegibilidad de {{ $equipo->nombre }}</h2>
            <p class="text-sm text-gray-400">Club {{ $equipo->club?->nombre }} · {{ $equipo->grupo?->categoria?->nombre }}. Se revisa al momento: padrón, sanciones, cuota, registro y categoría.</p>
        </div>
        <a href="{{ route('equipos.historial.show', $equipo) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver al equipo
        </a>
    </div>

    @php
        $bloqueados = $jugadores->where('estado', '!=', \App\Services\ElegibilidadJugadorService::HABILITADO)->count();
    @endphp
    @if ($bloqueados > 0)
        <div role="alert" class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
            {{ $bloqueados }} jugador(es) no se pueden alinear todavía. Revisa los motivos antes del partido.
        </div>
    @endif

    @if ($jugadores->isEmpty())
        <p class="text-sm text-gray-400">El equipo no tiene jugadores en su plantilla.</p>
    @else
        <x-table caption="Elegibilidad de la plantilla">
            <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                <tr>
                    <th scope="col" class="px-4 py-3">Dorsal</th>
                    <th scope="col" class="px-4 py-3">Jugador</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-4 py-3">Motivo y regla</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @foreach ($jugadores as $fila)
                    <tr data-jugador="{{ $fila['jugador']->id }}">
                        <td class="px-4 py-3">{{ $fila['jugador']->dorsal ?? '—' }}</td>
                        <td class="px-4 py-3 text-white">{{ $fila['jugador']->persona?->nombre }} {{ $fila['jugador']->persona?->apellidos }}</td>
                        <td class="px-4 py-3">
                            @switch($fila['estado'])
                                @case(\App\Services\ElegibilidadJugadorService::HABILITADO)
                                    <x-badge variant="success">Habilitado</x-badge>
                                    @break
                                @case(\App\Services\ElegibilidadJugadorService::NO_VERIFICADO)
                                    <x-badge variant="warning">No verificado</x-badge>
                                    @break
                                @default
                                    <x-badge variant="danger">No elegible</x-badge>
                            @endswitch
                        </td>
                        <td class="px-4 py-3">
                            @forelse ($fila['motivos'] as $motivo)
                                <span class="block">{{ $motivo['motivo'] }} <span class="text-xs text-gray-400">({{ $motivo['regla'] }})</span></span>
                            @empty
                                <span class="text-gray-400">—</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif
</div>
@endsection
