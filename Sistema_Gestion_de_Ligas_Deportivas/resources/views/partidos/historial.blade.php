@extends('layouts.admin')

@section('title', 'Historial del Partido - SGLD')
@section('header', 'Historial y Auditoría de Partidos')

@section('content')
@php
    $jornada = $partido->jornada;
    $grupo = $jornada->grupo;
@endphp

<div class="space-y-6 max-w-5xl">
    {{-- Encabezado --}}
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-white">Historial de Partido (HU-029 / HU-031)</h2>
                    <x-badge variant="info">{{ $partido->estado }}</x-badge>
                </div>
                <p class="text-sm text-gray-400 mt-1">
                    {{ $partido->local->nombre ?? 'Local' }} vs {{ $partido->visitante->nombre ?? 'Visitante' }} · Jornada {{ $jornada->numero_jornada }} ({{ $grupo->nombre }} - {{ $grupo->categoria->nombre }})
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Programación actual: {{ $partido->fecha_partido ? \Carbon\Carbon::parse($partido->fecha_partido)->format('d/m/Y') : 'Sin fecha' }}
                    {{ $partido->hora_partido ? \Carbon\Carbon::parse($partido->hora_partido)->format('H:i').' hrs' : '' }}
                    · Cancha: {{ $partido->cancha->nombre ?? 'Sin cancha' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('partidos.show', $partido) }}" class="text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm px-3 py-2">
                    Ver Partido
                </a>
                <a href="{{ route('jornadas.show', $jornada) }}" class="text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm px-3 py-2">
                    Volver a Jornada
                </a>
                @can('reprogramar', $partido)
                    <a href="{{ route('partidos.reprogramar', $partido) }}" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                        Reprogramar
                    </a>
                @endcan
            </div>
        </div>
    </div>

    {{-- Bitácora de reprogramaciones y cambios de estado (RNF-22) --}}
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 space-y-4">
        <div>
            <h3 class="text-lg font-bold text-white">Bitácora de Reprogramaciones y Cambios de Estado</h3>
            <p class="text-xs text-gray-400">
                Registro inmutable auditable con usuario, fechas, estados y justificación obligatoria (RNF-22 / HU-031 CA3).
            </p>
        </div>

        @if ($partido->reprogramaciones->isEmpty())
            <div class="bg-gray-900/50 border border-gray-700 rounded-xl p-8 text-center text-gray-400">
                <p class="text-base font-medium text-gray-300">Sin modificaciones registradas</p>
                <p class="text-xs text-gray-500 mt-1">Este partido conserva su programación original sin reprogramaciones ni cambios de estado posteriores.</p>
            </div>
        @else
            <x-table caption="Bitácora de modificaciones del partido">
                <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">Fecha y Hora Registro</th>
                        <th scope="col" class="px-4 py-3 text-left">Usuario</th>
                        <th scope="col" class="px-4 py-3 text-left">Transición de Estado</th>
                        <th scope="col" class="px-4 py-3 text-left">Cancha (Ant. &rarr; Nva.)</th>
                        <th scope="col" class="px-4 py-3 text-left">Horario (Ant. &rarr; Nvo.)</th>
                        <th scope="col" class="px-4 py-3 text-left">Motivo Registrado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($partido->reprogramaciones as $reprog)
                        <tr class="hover:bg-gray-750">
                            <td class="px-4 py-3 text-sm text-gray-300 whitespace-nowrap">
                                <div>{{ $reprog->created_at->format('d/m/Y') }}</div>
                                <div class="text-xs text-gray-500">{{ $reprog->created_at->format('H:i:s') }} hrs</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300 whitespace-nowrap">
                                <span class="font-medium text-white">{{ $reprog->usuario->name ?? 'Usuario #'.$reprog->usuario_id }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="text-xs text-gray-400">{{ $reprog->estado_anterior ?? 'N/A' }}</span>
                                <span class="text-gray-500 mx-1">&rarr;</span>
                                <span class="text-xs font-semibold text-purple-300">{{ $reprog->estado_nuevo }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                @if ($reprog->cancha_anterior_id !== $reprog->cancha_nueva_id)
                                    <div class="text-xs text-gray-400 line-through">{{ $reprog->canchaAnterior->nombre ?? 'Sin cancha' }}</div>
                                    <div class="text-xs font-medium text-green-400">{{ $reprog->canchaNueva->nombre ?? 'Sin cancha' }}</div>
                                @else
                                    <span class="text-xs text-gray-400">{{ $reprog->canchaNueva->nombre ?? 'Sin cambios' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300 whitespace-nowrap">
                                @if ($reprog->fecha_anterior !== $reprog->fecha_nueva || $reprog->hora_anterior !== $reprog->hora_nueva)
                                    <div class="text-xs text-gray-400 line-through">
                                        {{ $reprog->fecha_anterior ? \Carbon\Carbon::parse($reprog->fecha_anterior)->format('d/m/Y') : '-' }}
                                        {{ $reprog->hora_anterior ? \Carbon\Carbon::parse($reprog->hora_anterior)->format('H:i') : '' }}
                                    </div>
                                    <div class="text-xs font-medium text-green-400">
                                        {{ $reprog->fecha_nueva ? \Carbon\Carbon::parse($reprog->fecha_nueva)->format('d/m/Y') : '-' }}
                                        {{ $reprog->hora_nueva ? \Carbon\Carbon::parse($reprog->hora_nueva)->format('H:i') : '' }}
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Sin cambios de fecha</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300 max-w-xs break-words">
                                <span class="italic text-gray-200">"{{ $reprog->motivo }}"</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </div>
</div>
@endsection
