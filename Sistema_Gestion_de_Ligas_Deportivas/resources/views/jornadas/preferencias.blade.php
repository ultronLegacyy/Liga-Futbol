@extends('layouts.admin')

@section('title', 'Preferencias de Equipos - '.$grupo->nombre.' - SGLD')
@section('header', 'Preferencias de Programación')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Preferencias por Equipo · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">
                {{ $grupo->categoria->nombre }} · {{ $grupo->temporada->nombre }}
            </p>
            <p class="text-xs text-gray-400 mt-1">
                Cancha, día y horario solicitados por los clubes como criterio de prioridad no bloqueante para la asignación automática.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('grupos.jornadas.index', $grupo) }}" class="text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm px-3 py-2">
                Volver a las jornadas
            </a>
            <a href="{{ route('grupos.generar-rol', $grupo) }}" class="px-4 py-2 bg-indigo-600 text-white font-medium text-sm rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition">
                Generar rol automático
            </a>
        </div>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if ($errors->any())
        <x-alert type="error" :message="$errors->first()" />
    @endif

    <form action="{{ route('grupos.preferencias.update', $grupo) }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="overflow-x-auto border border-gray-700 rounded-xl">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-900/70 text-xs uppercase text-gray-400 border-b border-gray-700">
                    <tr>
                        <th class="px-4 py-3">Equipo</th>
                        <th class="px-4 py-3">Cancha preferida</th>
                        <th class="px-4 py-3">Día preferido</th>
                        <th class="px-4 py-3">Hora preferida (HH:MM)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/70">
                    @forelse ($equipos as $equipo)
                        @php
                            $pref = $equipo->preferencia;
                            $canchaVal = old("preferencias.{$equipo->id}.cancha_id_preferida", $pref?->cancha_id_preferida);
                            $diaVal = old("preferencias.{$equipo->id}.dia_preferido", $pref?->dia_preferido);
                            $horaVal = old("preferencias.{$equipo->id}.hora_preferida", $pref?->horaNormalizada());
                        @endphp
                        <tr class="hover:bg-gray-900/30">
                            <td class="px-4 py-3">
                                <div class="font-medium text-white">{{ $equipo->nombre }}</div>
                                <div class="text-xs text-gray-400">{{ $equipo->club?->nombre ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <select
                                    name="preferencias[{{ $equipo->id }}][cancha_id_preferida]"
                                    class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                                >
                                    <option value="">Sin preferencia</option>
                                    @foreach ($canchas as $cancha)
                                        <option value="{{ $cancha->id }}" @selected((string) $canchaVal === (string) $cancha->id)>
                                            {{ $cancha->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <select
                                    name="preferencias[{{ $equipo->id }}][dia_preferido]"
                                    class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                                >
                                    <option value="">Sin preferencia</option>
                                    @foreach ($diasSemana as $dia)
                                        <option value="{{ $dia }}" @selected($diaVal === $dia)>
                                            {{ $dia }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <input
                                    type="text"
                                    name="preferencias[{{ $equipo->id }}][hora_preferida]"
                                    value="{{ $horaVal }}"
                                    placeholder="Ej. 09:00"
                                    class="w-36 rounded-lg bg-gray-900 border border-gray-600 text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400"
                                >
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">
                                No hay equipos inscritos en este grupo todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($equipos->isNotEmpty())
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                    Guardar preferencias del grupo
                </button>
            </div>
        @endif
    </form>
</div>
@endsection
