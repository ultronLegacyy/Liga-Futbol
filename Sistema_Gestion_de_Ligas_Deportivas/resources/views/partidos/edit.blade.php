@extends('layouts.admin')

@section('title', 'Editar Partido - Jornada '.$jornada->numero_jornada.' - SGLD')
@section('header', 'Programación de Partidos')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Editar / Reprogramar Partido (RNF-29, RNF-33)</h2>
            <p class="text-sm text-gray-400">
                Jornada {{ $jornada->numero_jornada }} · {{ $grupo->nombre }} ({{ $grupo->categoria->nombre }})
            </p>
        </div>
        <a href="{{ route('jornadas.show', $jornada) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm">
            Volver a la jornada
        </a>
    </div>

    @if ($jornada->equipoDescanso)
        <div class="bg-blue-900/30 border border-blue-500/30 rounded-lg p-3 text-sm text-blue-300">
            <strong>Aviso de descanso:</strong> El equipo <span class="font-bold underline">{{ $jornada->equipoDescanso->nombre }}</span> descansa en esta jornada (RF-06.2).
        </div>
    @endif

    @if ($errors->any())
        <x-alert type="error" message="No se pudo actualizar el partido. Revisa los errores señalados." />
    @endif

    <form method="POST" action="{{ route('partidos.update', $partido) }}" novalidate class="space-y-5">
        @csrf
        @method('PUT')

        {{-- Equipos Local y Visitante --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="equipo_local_id" label="Equipo Local" required :error="$errors->first('equipo_local_id')">
                <select
                    id="equipo_local_id"
                    name="equipo_local_id"
                    required
                    class="{{ $campo }} {{ $errors->has('equipo_local_id') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('equipo_local_id')) aria-invalid="true" aria-describedby="equipo_local_id-error" @endif
                >
                    <option value="">Selecciona equipo local</option>
                    @foreach ($equipos as $equipo)
                        <option
                            value="{{ $equipo->id }}"
                            @selected(old('equipo_local_id', $partido->equipo_local_id) == $equipo->id)
                            @disabled($jornada->equipo_descanso_id === $equipo->id)
                        >
                            {{ $equipo->nombre }} {{ $jornada->equipo_descanso_id === $equipo->id ? '(En descanso)' : '' }}
                        </option>
                    @endforeach
                </select>
            </x-form.field>

            <x-form.field name="equipo_visitante_id" label="Equipo Visitante" required :error="$errors->first('equipo_visitante_id')">
                <select
                    id="equipo_visitante_id"
                    name="equipo_visitante_id"
                    required
                    class="{{ $campo }} {{ $errors->has('equipo_visitante_id') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('equipo_visitante_id')) aria-invalid="true" aria-describedby="equipo_visitante_id-error" @endif
                >
                    <option value="">Selecciona equipo visitante</option>
                    @foreach ($equipos as $equipo)
                        <option
                            value="{{ $equipo->id }}"
                            @selected(old('equipo_visitante_id', $partido->equipo_visitante_id) == $equipo->id)
                            @disabled($jornada->equipo_descanso_id === $equipo->id)
                        >
                            {{ $equipo->nombre }} {{ $jornada->equipo_descanso_id === $equipo->id ? '(En descanso)' : '' }}
                        </option>
                    @endforeach
                </select>
            </x-form.field>
        </div>

        {{-- Cancha y Árbitro --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="cancha_id" label="Cancha Asignada" required :error="$errors->first('cancha_id')">
                <select
                    id="cancha_id"
                    name="cancha_id"
                    required
                    class="{{ $campo }} {{ $errors->has('cancha_id') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('cancha_id')) aria-invalid="true" aria-describedby="cancha_id-error" @endif
                >
                    <option value="">Selecciona una cancha</option>
                    @foreach ($canchas as $cancha)
                        <option value="{{ $cancha->id }}" @selected(old('cancha_id', $partido->cancha_id) == $cancha->id)>
                            {{ $cancha->nombre }} (Cap. {{ $cancha->capacidad_partidos_consecutivos }})
                        </option>
                    @endforeach
                </select>
            </x-form.field>

            <x-form.field name="arbitro_id" label="Árbitro Asignado" required :error="$errors->first('arbitro_id')">
                <select
                    id="arbitro_id"
                    name="arbitro_id"
                    required
                    class="{{ $campo }} {{ $errors->has('arbitro_id') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('arbitro_id')) aria-invalid="true" aria-describedby="arbitro_id-error" @endif
                >
                    <option value="">Selecciona un árbitro</option>
                    @foreach ($arbitros as $arbitro)
                        <option value="{{ $arbitro->id }}" @selected(old('arbitro_id', $partido->arbitro_id) == $arbitro->id)>
                            {{ $arbitro->persona->nombre_completo ?? 'Árbitro #'.$arbitro->id }}
                        </option>
                    @endforeach
                </select>
            </x-form.field>
        </div>

        {{-- Fecha y Hora --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="fecha_partido" label="Fecha del Partido" required :error="$errors->first('fecha_partido')">
                <input
                    type="date"
                    id="fecha_partido"
                    name="fecha_partido"
                    required
                    min="{{ \Carbon\Carbon::parse($partido->jornada->fecha_inicio)->format('Y-m-d') }}"
                    max="{{ \Carbon\Carbon::parse($partido->jornada->fecha_fin)->format('Y-m-d') }}"
                    value="{{ is_string(old('fecha_partido')) ? old('fecha_partido') : ($partido->fecha_partido ? \Carbon\Carbon::parse($partido->fecha_partido)->format('Y-m-d') : '') }}"
                    class="{{ $campo }} {{ $errors->has('fecha_partido') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('fecha_partido')) aria-invalid="true" aria-describedby="fecha_partido-error" @endif
                >
            </x-form.field>

            <x-form.field name="hora_partido" label="Hora del Partido" required :error="$errors->first('hora_partido')">
                <input
                    type="time"
                    id="hora_partido"
                    name="hora_partido"
                    required
                    value="{{ is_string(old('hora_partido')) ? old('hora_partido') : ($partido->hora_partido ? \Carbon\Carbon::parse($partido->hora_partido)->format('H:i') : '') }}"
                    class="{{ $campo }} {{ $errors->has('hora_partido') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('hora_partido')) aria-invalid="true" aria-describedby="hora_partido-error" @endif
                >
            </x-form.field>
        </div>

        {{-- Motivo de reprogramación si el partido ya no es borrador (HU-029) --}}
        @if (! $partido->esBorrador())
            <x-form.field name="motivo" label="Motivo de reprogramación (Obligatorio si cambia fecha, hora o cancha, mín. 10 caracteres)" :error="$errors->first('motivo')">
                <textarea
                    id="motivo"
                    name="motivo"
                    rows="3"
                    placeholder="Registra el motivo por el cual se modifica la fecha, hora o cancha de este partido..."
                    class="{{ $campo }} {{ $errors->has('motivo') ? 'border-red-500' : 'border-gray-600' }}"
                >{{ is_string(old('motivo')) ? old('motivo') : '' }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Este partido se encuentra en estado '{{ $partido->estado }}'. Todo cambio de programación quedará auditado en la bitácora oficial (HU-029).</p>
            </x-form.field>
        @endif

        <div class="flex items-center gap-4 pt-4 border-t border-gray-700">
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
                Actualizar Partido
            </button>
            <a href="{{ route('jornadas.show', $jornada) }}" class="text-sm text-gray-400 hover:text-white transition">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
