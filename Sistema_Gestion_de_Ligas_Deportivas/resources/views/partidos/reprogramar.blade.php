@extends('layouts.admin')

@section('title', 'Reprogramar Partido - SGLD')
@section('header', 'Reprogramación de Partidos')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
    $jornada = $partido->jornada;
    $grupo = $jornada->grupo;
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Reprogramar Partido (HU-029 / SCRUM-196)</h2>
            <p class="text-sm text-gray-400">
                Jornada {{ $jornada->numero_jornada }} · {{ $grupo->nombre }} ({{ $grupo->categoria->nombre }})
            </p>
        </div>
        <a href="{{ route('jornadas.show', $jornada) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm">
            Volver a la jornada
        </a>
    </div>

    {{-- Resumen del enfrentamiento actual --}}
    <div class="bg-gray-900/60 border border-gray-700 rounded-xl p-4 text-center">
        <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-3">
            <div>
                <span class="text-xs uppercase text-gray-400">Local</span>
                <p class="text-lg font-bold text-green-400">{{ $partido->local->nombre ?? 'Local' }}</p>
            </div>
            <div>
                <span class="inline-block px-3 py-1 bg-gray-800 text-gray-300 rounded-full font-bold text-xs border border-gray-700">
                    VS
                </span>
                <div class="mt-1">
                    <x-badge variant="info">{{ $partido->estado }}</x-badge>
                </div>
            </div>
            <div>
                <span class="text-xs uppercase text-gray-400">Visitante</span>
                <p class="text-lg font-bold text-blue-400">{{ $partido->visitante->nombre ?? 'Visitante' }}</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <x-alert type="error" message="No se pudo reprogramar el partido. Revisa los errores señalados abajo." />
    @endif

    <form method="POST" action="{{ route('partidos.reprogramar.store', $partido) }}" novalidate class="space-y-5">
        @csrf

        {{-- Nueva fecha y hora --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="fecha_partido" label="Nueva Fecha del Partido" required :error="$errors->first('fecha_partido')">
                <input
                    type="date"
                    id="fecha_partido"
                    name="fecha_partido"
                    required
                    value="{{ is_string(old('fecha_partido')) ? old('fecha_partido') : ($partido->fecha_partido ? \Carbon\Carbon::parse($partido->fecha_partido)->format('Y-m-d') : '') }}"
                    class="{{ $campo }} {{ $errors->has('fecha_partido') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('fecha_partido')) aria-invalid="true" aria-describedby="fecha_partido-error" @endif
                >
            </x-form.field>

            <x-form.field name="hora_partido" label="Nueva Hora del Partido" required :error="$errors->first('hora_partido')">
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

        {{-- Nueva cancha y árbitro --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="cancha_id" label="Cancha Asignada" required :error="$errors->first('cancha_id')">
                <select
                    id="cancha_id"
                    name="cancha_id"
                    required
                    class="{{ $campo }} {{ $errors->has('cancha_id') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('cancha_id')) aria-invalid="true" aria-describedby="cancha_id-error" @endif
                >
                    <option value="">-- Seleccionar cancha oficial --</option>
                    @foreach ($canchas as $cancha)
                        <option value="{{ $cancha->id }}" @selected((is_string(old('cancha_id')) ? old('cancha_id') : (string) $partido->cancha_id) == (string) $cancha->id)>
                            {{ $cancha->nombre }} (Cap. {{ $cancha->capacidad_partidos_consecutivos }})
                        </option>
                    @endforeach
                </select>
            </x-form.field>

            <x-form.field name="arbitro_id" label="Árbitro Asignado" :error="$errors->first('arbitro_id')">
                <select
                    id="arbitro_id"
                    name="arbitro_id"
                    class="{{ $campo }} {{ $errors->has('arbitro_id') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('arbitro_id')) aria-invalid="true" aria-describedby="arbitro_id-error" @endif
                >
                    <option value="">-- Sin árbitro asignado --</option>
                    @foreach ($arbitros as $arbitro)
                        <option value="{{ $arbitro->id }}" @selected(old('arbitro_id', $partido->arbitro_id) == $arbitro->id)>
                            {{ $arbitro->persona->nombre_completo ?? 'Árbitro #'.$arbitro->id }}
                        </option>
                    @endforeach
                </select>
            </x-form.field>
        </div>

        {{-- Nuevo estado tras reprogramar --}}
        @php
            $opcionesEstado = [];
            if ($partido->estado === \App\Models\Partido::ESTADO_REPROGRAMADO) {
                $opcionesEstado[\App\Models\Partido::ESTADO_REPROGRAMADO] = 'reprogramado (Mantener)';
            } else {
                if ($partido->puedeTransicionarA(\App\Models\Partido::ESTADO_REPROGRAMADO)) {
                    $opcionesEstado[\App\Models\Partido::ESTADO_REPROGRAMADO] = 'reprogramado (Recomendado)';
                }
            }
            if ($partido->puedeTransicionarA(\App\Models\Partido::ESTADO_PROGRAMADO)) {
                $opcionesEstado[\App\Models\Partido::ESTADO_PROGRAMADO] = 'programado';
            }
            if ($partido->puedeTransicionarA(\App\Models\Partido::ESTADO_CONFIRMADO)) {
                $opcionesEstado[\App\Models\Partido::ESTADO_CONFIRMADO] = 'confirmado';
            }
            if (empty($opcionesEstado)) {
                $opcionesEstado[\App\Models\Partido::ESTADO_REPROGRAMADO] = 'reprogramado';
            }
        @endphp
        <div>
            <x-form.field name="estado" label="Estado Posterior" :error="$errors->first('estado')">
                <select
                    id="estado"
                    name="estado"
                    class="{{ $campo }} {{ $errors->has('estado') ? 'border-red-500' : 'border-gray-600' }}"
                >
                    @foreach ($opcionesEstado as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected((is_string(old('estado')) ? old('estado') : array_key_first($opcionesEstado)) === $valor)>
                            {{ $etiqueta }}
                        </option>
                    @endforeach
                </select>
            </x-form.field>
        </div>

        {{-- Motivo obligatorio (RNF-22 / HU-029 CA2) --}}
        <x-form.field name="motivo" label="Motivo de la Reprogramación (Obligatorio, mín. 10 caracteres)" required :error="$errors->first('motivo')">
            <textarea
                id="motivo"
                name="motivo"
                rows="4"
                required
                minlength="10"
                placeholder="Indica la razón formal de la reprogramación (ej. condiciones climáticas adversas, mantenimiento urgente en cancha, solicitud consensuada entre clubes)..."
                class="{{ $campo }} {{ $errors->has('motivo') ? 'border-red-500' : 'border-gray-600' }}"
                @if ($errors->has('motivo')) aria-invalid="true" aria-describedby="motivo-error" @endif
            >{{ is_string(old('motivo')) ? old('motivo') : '' }}</textarea>
            <p class="text-xs text-gray-400 mt-1">Este motivo quedará auditado de forma permanente en la bitácora histórica del partido (HU-031 / RNF-22).</p>
        </x-form.field>

        <div class="flex items-center gap-4 pt-4 border-t border-gray-700">
            <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white font-medium text-sm rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-400 transition">
                Confirmar Reprogramación
            </button>
            <a href="{{ route('jornadas.show', $jornada) }}" class="text-sm text-gray-400 hover:text-white transition">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
