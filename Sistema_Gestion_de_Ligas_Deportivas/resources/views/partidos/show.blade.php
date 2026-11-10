@extends('layouts.admin')

@section('title', 'Detalle de Partido - SGLD')
@section('header', 'Programación de Partidos')

@section('content')
@php
    $jornada = $partido->jornada;
    $grupo = $jornada->grupo;
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Detalle de Partido (IU-16)</h2>
            <p class="text-sm text-gray-400">
                Jornada {{ $jornada->numero_jornada }} · {{ $grupo->nombre }} ({{ $grupo->categoria->nombre }})
            </p>
        </div>
        <a href="{{ route('jornadas.show', $jornada) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm">
            Volver a la jornada
        </a>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    {{-- Enfrentamiento visual --}}
    <div class="bg-gray-900/60 border border-gray-700 rounded-xl p-6 text-center">
        <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-4">
            <div>
                <span class="text-xs uppercase tracking-wider text-gray-400">Local</span>
                <p class="text-xl font-bold text-green-400 mt-1">{{ $partido->local->nombre ?? 'Equipo local' }}</p>
            </div>
            <div>
                <span class="inline-block px-3 py-1 bg-gray-800 text-gray-300 rounded-full font-bold text-sm border border-gray-700">
                    VS
                </span>
                <div class="mt-2">
                    <x-badge variant="info">{{ $partido->estado }}</x-badge>
                </div>
            </div>
            <div>
                <span class="text-xs uppercase tracking-wider text-gray-400">Visitante</span>
                <p class="text-xl font-bold text-blue-400 mt-1">{{ $partido->visitante->nombre ?? 'Equipo visitante' }}</p>
            </div>
        </div>
    </div>

    {{-- Datos de programación --}}
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Fecha y Hora</dt>
            <dd class="mt-1 text-white font-medium">
                {{ $partido->fecha_partido ? \Carbon\Carbon::parse($partido->fecha_partido)->format('d/m/Y') : 'Sin fecha' }}
                @if ($partido->hora_partido)
                    a las {{ \Carbon\Carbon::parse($partido->hora_partido)->format('H:i') }} hrs
                @else
                    · Sin hora
                @endif
            </dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Cancha Asignada</dt>
            <dd class="mt-1 text-white font-medium">
                {{ $partido->cancha?->nombre ?? 'Sin cancha asignada' }}
                @if ($partido->cancha?->direccion_ubicacion)
                    <span class="block text-xs text-gray-400 mt-0.5">{{ $partido->cancha->direccion_ubicacion }}</span>
                @endif
            </dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Árbitro Asignado</dt>
            <dd class="mt-1 text-white font-medium">
                {{ $partido->arbitro->persona->nombre_completo ?? 'Árbitro #'.$partido->arbitro_id }}
            </dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Temporada y Competencia</dt>
            <dd class="mt-1 text-white font-medium">
                {{ $grupo->temporada->nombre }} · {{ $grupo->categoria->nombre }} ({{ $grupo->nombre }})
            </dd>
        </div>
    </dl>

    <div class="flex items-center gap-4 pt-4 border-t border-gray-700">
        @can('update', $partido)
            <a href="{{ route('partidos.edit', $partido) }}" class="px-4 py-2 bg-yellow-600/90 hover:bg-yellow-600 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 transition">
                Editar / Reprogramar
            </a>
        @endcan

        @can('delete', $partido)
            <form action="{{ route('partidos.destroy', $partido) }}" method="POST" class="inline" onsubmit="return confirm('¿Deseas cancelar/eliminar este partido programado?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600/90 hover:bg-red-600 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 transition">
                    Eliminar partido
                </button>
            </form>
        @endcan

        <a href="{{ route('jornadas.show', $jornada) }}" class="text-sm text-gray-400 hover:text-white transition ml-auto">
            Volver a la jornada
        </a>
    </div>
</div>
@endsection
