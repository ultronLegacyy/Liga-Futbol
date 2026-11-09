@extends('layouts.admin')

@section('title', 'Generar Rol Regular - '.$grupo->nombre.' - SGLD')
@section('header', 'Programación de Partidos')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-bold text-white">Generar Rol Regular de Enfrentamientos</h2>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                    HU-032 / RF-07.1
                </span>
            </div>
            <p class="text-sm text-gray-400 mt-1">
                {{ $grupo->nombre }} · {{ $grupo->categoria->nombre }} · {{ $grupo->temporada->nombre }}
            </p>
        </div>
        <a href="{{ route('grupos.jornadas.index', $grupo) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm px-3 py-2">
            Volver a las jornadas
        </a>
    </div>

    {{-- Resumen de conformación del grupo --}}
    <div class="bg-gray-900/60 border border-gray-700/80 rounded-lg p-4 space-y-2">
        <h3 class="text-sm font-semibold text-gray-200">Parámetros del Grupo</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-300">
            <div>
                <span class="text-gray-400 block">Total de equipos:</span>
                <span class="font-bold text-white text-sm">{{ $grupo->equipos->count() }} inscritos</span>
            </div>
            <div>
                <span class="text-gray-400 block">Modalidad de descansos:</span>
                @if ($grupo->tieneEquiposImpares())
                    <span class="font-bold text-yellow-400 text-sm">Impar (1 descanso por jornada)</span>
                @else
                    <span class="font-bold text-green-400 text-sm">Par (todos juegan cada jornada)</span>
                @endif
            </div>
            <div>
                <span class="text-gray-400 block">Algoritmo aplicado:</span>
                <span class="font-bold text-blue-300 text-sm">Round-Robin (Sistema Berger)</span>
            </div>
        </div>
    </div>

    @if ($jornadasExistentesCount > 0)
        <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4 text-sm text-yellow-300">
            <p class="font-semibold">Atención: Este grupo ya cuenta con {{ $jornadasExistentesCount }} jornada(s) registradas.</p>
            <p class="text-xs text-yellow-400/80 mt-1">
                Para generar un nuevo rol de enfrentamientos completo y reemplazar las existentes, marque la casilla de sobrescribir al final del formulario.
            </p>
        </div>
    @endif

    @if ($errors->any())
        <x-alert type="error" message="No se pudo procesar la solicitud. Revisa los campos señalados." />
    @endif

    <form method="POST" action="{{ route('grupos.previsualizar-rol', $grupo) }}" novalidate class="space-y-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="fecha_inicio" label="Fecha de Inicio del Torneo" required :error="$errors->first('fecha_inicio')" help="Fecha en que se disputará la Jornada 1.">
                <input
                    type="date"
                    id="fecha_inicio"
                    name="fecha_inicio"
                    required
                    value="{{ old('fecha_inicio', \Carbon\Carbon::now()->next(\Carbon\Carbon::SATURDAY)->toDateString()) }}"
                    class="{{ $campo }} {{ $errors->has('fecha_inicio') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('fecha_inicio')) aria-invalid="true" aria-describedby="fecha_inicio-error" @endif
                >
            </x-form.field>

            <x-form.field name="intervalo_dias" label="Intervalo entre Jornadas (días)" required :error="$errors->first('intervalo_dias')" help="Frecuencia de las jornadas (ej. 7 para semanal).">
                <input
                    type="number"
                    id="intervalo_dias"
                    name="intervalo_dias"
                    min="1"
                    max="60"
                    required
                    value="{{ old('intervalo_dias', 7) }}"
                    class="{{ $campo }} {{ $errors->has('intervalo_dias') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('intervalo_dias')) aria-invalid="true" aria-describedby="intervalo_dias-error" @endif
                >
            </x-form.field>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="dias_duracion" label="Duración por Jornada (días)" required :error="$errors->first('dias_duracion')" help="Días que abarca la jornada (ej. 2 para fin de semana sábado a domingo).">
                <input
                    type="number"
                    id="dias_duracion"
                    name="dias_duracion"
                    min="0"
                    max="30"
                    required
                    value="{{ old('dias_duracion', 2) }}"
                    class="{{ $campo }} {{ $errors->has('dias_duracion') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('dias_duracion')) aria-invalid="true" aria-describedby="dias_duracion-error" @endif
                >
            </x-form.field>

            <x-form.field name="vueltas" label="Formato de Competencia" required :error="$errors->first('vueltas')" help="Número de vueltas en el rol regular.">
                <select
                    id="vueltas"
                    name="vueltas"
                    required
                    class="{{ $campo }} {{ $errors->has('vueltas') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('vueltas')) aria-invalid="true" aria-describedby="vueltas-error" @endif
                >
                    <option value="1" {{ old('vueltas', 1) == 1 ? 'selected' : '' }}>1 Vuelta (Solo ida)</option>
                    <option value="2" {{ old('vueltas') == 2 ? 'selected' : '' }}>2 Vueltas (Ida y vuelta / Revanchas)</option>
                </select>
            </x-form.field>
        </div>

        <div class="space-y-3 pt-2">
            <label class="flex items-center gap-3 text-sm text-gray-200 cursor-pointer">
                <input
                    type="checkbox"
                    id="aleatorizar"
                    name="aleatorizar"
                    value="1"
                    {{ old('aleatorizar') ? 'checked' : '' }}
                    class="rounded bg-gray-900 border-gray-600 text-green-500 focus:ring-green-400 w-4 h-4"
                >
                <span>Sorteo aleatorio de emparejamientos iniciales (mantiene balance de localías)</span>
            </label>

            @if ($jornadasExistentesCount > 0)
                <label class="flex items-center gap-3 text-sm text-yellow-300 font-semibold cursor-pointer">
                    <input
                        type="checkbox"
                        id="sobrescribir"
                        name="sobrescribir"
                        value="1"
                        {{ old('sobrescribir') ? 'checked' : '' }}
                        class="rounded bg-gray-900 border-yellow-500 text-yellow-500 focus:ring-yellow-400 w-4 h-4"
                    >
                    <span>Confirmar reemplazo de las {{ $jornadasExistentesCount }} jornadas existentes en este grupo</span>
                </label>
            @endif
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-700">
            <a href="{{ route('grupos.jornadas.index', $grupo) }}" class="px-4 py-2 text-sm text-gray-300 hover:text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-400">
                Cancelar
            </a>
            <button
                type="submit"
                class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition"
            >
                Calcular y Previsualizar Rol
            </button>
        </div>
    </form>
</div>
@endsection
