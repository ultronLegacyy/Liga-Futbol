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
                    @php
                        $estadoPartidoVariant = match($partido->estado) {
                            'confirmado' => 'success',
                            'cancelado' => 'danger',
                            'suspendido', 'en juego' => 'warning',
                            'programado', 'reprogramado' => 'info',
                            default => 'default',
                        };
                    @endphp
                    <x-badge :variant="$estadoPartidoVariant">{{ $partido->estado }}</x-badge>
                </div>
            </div>
            <div>
                <span class="text-xs uppercase tracking-wider text-gray-400">Visitante</span>
                <p class="text-xl font-bold text-blue-400 mt-1">{{ $partido->visitante->nombre ?? 'Equipo visitante' }}</p>
            </div>
        </div>
    </div>

    {{-- Cambio de estado del partido (HU-029 / SCRUM-195) --}}
    @can('cambiarEstado', $partido)
        @php
            $permitidos = $partido->transicionesPermitidas();
        @endphp
        @if (! empty($permitidos))
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4 space-y-3">
                <h3 class="text-sm font-semibold text-white">Transición de Estado (HU-029)</h3>
                <form action="{{ route('partidos.estado.update', $partido) }}" method="POST" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="nuevo_estado" class="block text-xs text-gray-300 font-medium mb-1">Nuevo Estado</label>
                            <select
                                name="estado"
                                id="nuevo_estado"
                                required
                                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white text-sm p-2 focus:ring-2 focus:ring-green-400 focus:outline-none"
                            >
                                <option value="">Selecciona nuevo estado</option>
                                @foreach ($permitidos as $opcion)
                                    <option value="{{ $opcion }}">{{ ucfirst($opcion) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="motivo_estado" class="block text-xs text-gray-300 font-medium mb-1">Motivo / Justificación</label>
                            <input
                                type="text"
                                name="motivo"
                                id="motivo_estado"
                                placeholder="Requerido para cancelado, suspendido o reprogramado (mín. 5 letras)"
                                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white text-sm p-2 focus:ring-2 focus:ring-green-400 focus:outline-none"
                            >
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 transition">
                        Actualizar Estado
                    </button>
                </form>
            </div>
        @endif
    @endcan

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

    <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-700">
        <a href="{{ route('partidos.historial', $partido) }}" class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition" title="Consultar bitácora histórica">
            Ver Historial
        </a>

        @can('reprogramar', $partido)
            <a href="{{ route('partidos.reprogramar', $partido) }}" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                Reprogramar
            </a>
        @endcan

        @can('update', $partido)
            <a href="{{ route('partidos.edit', $partido) }}" class="px-3 py-2 bg-yellow-600/90 hover:bg-yellow-600 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 transition">
                Editar
            </a>
        @endcan

        @can('delete', $partido)
            <form action="{{ route('partidos.destroy', $partido) }}" method="POST" class="inline" onsubmit="return confirm('¿Deseas eliminar este partido en borrador?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-2 bg-red-600/90 hover:bg-red-600 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 transition">
                    Eliminar
                </button>
            </form>
        @else
            <span class="text-xs text-gray-500 italic p-2" title="Historial oficial protegido contra eliminación (HU-031)">
                Historial protegido
            </span>
        @endcan

        <a href="{{ route('jornadas.show', $jornada) }}" class="text-sm text-gray-400 hover:text-white transition ml-auto">
            Volver a la jornada
        </a>
    </div>
</div>
@endsection
