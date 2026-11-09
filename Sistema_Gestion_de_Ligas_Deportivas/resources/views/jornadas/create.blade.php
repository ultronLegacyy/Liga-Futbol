@extends('layouts.admin')

@section('title', 'Nueva Jornada - '.$grupo->nombre.' - SGLD')
@section('header', 'Programación de Partidos')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Nueva Jornada · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">{{ $grupo->categoria->nombre }} · {{ $grupo->temporada->nombre }}</p>
        </div>
        <a href="{{ route('grupos.jornadas.index', $grupo) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm">
            Volver a las jornadas
        </a>
    </div>

    @if ($errors->any())
        <x-alert type="error" message="No se pudo registrar la jornada. Revisa los campos señalados." />
    @endif

    <form method="POST" action="{{ route('grupos.jornadas.store', $grupo) }}" novalidate class="space-y-5">
        @csrf

        <x-form.field name="numero_jornada" label="Número de Jornada" required :error="$errors->first('numero_jornada')">
            <input
                type="number"
                id="numero_jornada"
                name="numero_jornada"
                min="1"
                required
                autofocus
                value="{{ old('numero_jornada', ($grupo->jornadas()->max('numero_jornada') ?? 0) + 1) }}"
                class="{{ $campo }} {{ $errors->has('numero_jornada') ? 'border-red-500' : 'border-gray-600' }}"
                @if ($errors->has('numero_jornada')) aria-invalid="true" aria-describedby="numero_jornada-error" @endif
            >
        </x-form.field>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="fecha_inicio" label="Fecha de Inicio" required :error="$errors->first('fecha_inicio')">
                <input
                    type="date"
                    id="fecha_inicio"
                    name="fecha_inicio"
                    required
                    value="{{ old('fecha_inicio') }}"
                    class="{{ $campo }} {{ $errors->has('fecha_inicio') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('fecha_inicio')) aria-invalid="true" aria-describedby="fecha_inicio-error" @endif
                >
            </x-form.field>

            <x-form.field name="fecha_fin" label="Fecha de Fin" required :error="$errors->first('fecha_fin')">
                <input
                    type="date"
                    id="fecha_fin"
                    name="fecha_fin"
                    required
                    value="{{ old('fecha_fin') }}"
                    class="{{ $campo }} {{ $errors->has('fecha_fin') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($errors->has('fecha_fin')) aria-invalid="true" aria-describedby="fecha_fin-error" @endif
                >
            </x-form.field>
        </div>

        <x-form.field
            name="equipo_descanso_id"
            label="Equipo que descansa (RF-06.2)"
            help="{{ $grupo->tieneEquiposImpares() ? 'El grupo tiene un número impar de equipos. Se recomienda registrar qué equipo descansa en esta jornada.' : 'Opcional. Para grupos pares normalmente no se requiere descanso.' }}"
            :error="$errors->first('equipo_descanso_id')"
        >
            <select
                id="equipo_descanso_id"
                name="equipo_descanso_id"
                class="{{ $campo }} {{ $errors->has('equipo_descanso_id') ? 'border-red-500' : 'border-gray-600' }}"
                @if ($errors->has('equipo_descanso_id')) aria-invalid="true" aria-describedby="equipo_descanso_id-error" @endif
            >
                <option value="">-- Ninguno / Sin descanso --</option>
                @foreach ($equipos as $equipo)
                    <option value="{{ $equipo->id }}" @selected(old('equipo_descanso_id') == $equipo->id)>
                        {{ $equipo->nombre }}
                    </option>
                @endforeach
            </select>
        </x-form.field>

        <div class="flex items-center gap-4 pt-4 border-t border-gray-700">
            <button type="submit" class="px-5 py-2.5 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                Crear Jornada
            </button>
            <a href="{{ route('grupos.jornadas.index', $grupo) }}" class="text-sm text-gray-400 hover:text-white transition">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
