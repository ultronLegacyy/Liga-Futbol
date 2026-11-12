@extends('layouts.admin')

@section('title', 'Cédula arbitral - SGLD')
@section('header', 'Cédula arbitral')

@section('content')
@php
    $nombre = fn ($jugador) => $jugador?->persona ? $jugador->persona->apellidos.', '.$jugador->persona->nombre : 'Sin jugador';
    $equipos = [$partido->equipo_local_id => $partido->local?->nombre, $partido->equipo_visitante_id => $partido->visitante?->nombre];
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl">
    <h2 class="text-xl font-bold text-white mb-1">{{ $partido->local?->nombre }} vs. {{ $partido->visitante?->nombre }}</h2>
    <p class="text-sm text-gray-400 mb-6">
        Partido {{ $partido->id }} · {{ $partido->fecha_partido?->format('d/m/Y') ?? 'Sin fecha' }}
        {{ $partido->hora_partido ? '· '.substr((string) $partido->hora_partido, 0, 5) : '' }}
        · {{ $partido->cancha?->nombre ?? 'Sin cancha' }}
        · Árbitro: {{ $partido->arbitro?->persona ? $partido->arbitro->persona->nombre.' '.$partido->arbitro->persona->apellidos : 'Sin asignar' }}
    </p>

    @if (! $cedula)
        <x-alert type="info" message="El árbitro todavía no abre la cédula de este partido." />
    @else
        @if ($cedula->estaCerrada())
            <x-alert type="info" message="La cédula está cerrada: solo se consulta." />
        @endif

        <dl class="grid grid-cols-2 gap-4 text-center mb-8">
            <div class="bg-gray-900 rounded-lg p-4">
                <dt class="text-sm text-gray-400">{{ $partido->local?->nombre }}</dt>
                <dd class="text-4xl font-bold text-white">{{ $cedula->goles_local }}</dd>
            </div>
            <div class="bg-gray-900 rounded-lg p-4">
                <dt class="text-sm text-gray-400">{{ $partido->visitante?->nombre }}</dt>
                <dd class="text-4xl font-bold text-white">{{ $cedula->goles_visitante }}</dd>
            </div>
        </dl>

        <h3 class="text-lg font-semibold text-white mb-3">Alineaciones</h3>
        <div class="grid gap-6 md:grid-cols-2 mb-8">
            @foreach ($equipos as $equipoId => $equipoNombre)
                @php($filas = $cedula->alineaciones->where('equipo_id', $equipoId))
                <section aria-label="Alineación de {{ $equipoNombre }}">
                    <h4 class="font-semibold text-gray-200 mb-2">{{ $equipoNombre }}</h4>
                    @if ($filas->isEmpty())
                        <p class="text-sm text-gray-400">El equipo no tiene jugadores en su plantilla.</p>
                    @else
                        <ul class="divide-y divide-gray-700 text-sm">
                            @foreach ($filas as $fila)
                                <li class="flex items-center justify-between py-2 gap-3">
                                    <span class="text-white">
                                        <span class="inline-block w-8 text-gray-400">{{ $fila->numero_camiseta ?? '—' }}</span>
                                        {{ $nombre($fila->jugador) }}
                                        @if ($fila->es_capitan)
                                            <span class="ml-1 text-xs font-bold text-amber-400" title="Capitán">(C)<span class="sr-only"> capitán</span></span>
                                        @endif
                                    </span>
                                    <span class="{{ $fila->asistio ? 'text-green-400' : 'text-gray-500' }}">{{ $fila->asistio ? 'Asistió' : 'Sin marcar' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold text-white mb-3">Goles y tarjetas</h3>
        @if ($cedula->eventos->isEmpty())
            <p class="text-sm text-gray-400 mb-8">Todavía no hay goles ni tarjetas.</p>
        @else
            <ul class="divide-y divide-gray-700 text-sm mb-8">
                @foreach ($cedula->eventos as $evento)
                    <li class="py-2 text-white">
                        <span class="inline-block w-12 text-gray-400">{{ $evento->minuto !== null ? $evento->minuto."'" : '—' }}</span>
                        {{ $evento->tipo }}{{ $evento->autogol ? ' (autogol)' : '' }} · {{ $nombre($evento->jugador) }}
                    </li>
                @endforeach
            </ul>
        @endif

        <h3 class="text-lg font-semibold text-white mb-3">Incidencias</h3>
        @if ($cedula->incidencias->isEmpty())
            <p class="text-sm text-gray-400">Sin incidencias.</p>
        @else
            <ul class="divide-y divide-gray-700 text-sm">
                @foreach ($cedula->incidencias as $incidencia)
                    <li class="py-2 text-white">
                        <span class="inline-block w-12 text-gray-400">{{ $incidencia->minuto !== null ? $incidencia->minuto."'" : '—' }}</span>
                        {{ $incidencia->descripcion }}
                        @if ($incidencia->equipo)
                            <span class="text-gray-400">· {{ $incidencia->equipo->nombre }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
@endsection
