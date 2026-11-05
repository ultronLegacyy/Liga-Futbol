@extends('layouts.admin')

@section('title', 'Registrar persona - SGLD')
@section('header', 'Personas')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Registrar persona con un rol</h2>
    <p class="text-sm text-gray-400 mb-6">Si la CURP ya está registrada, se reutiliza la identidad existente y sus datos no cambian.</p>

    @if ($errors->any())
        <x-alert type="error" message="No se registró la persona. Revisa los campos marcados." />
    @endif

    <form method="POST" action="{{ route('personas.store') }}" novalidate class="space-y-5">
        @csrf

        <x-form.field name="curp" label="CURP" required help="18 caracteres, letras y números." :error="$errors->first('curp')">
            <input
                type="text"
                id="curp"
                name="curp"
                maxlength="18"
                required
                autofocus
                autocomplete="off"
                value="{{ old('curp') }}"
                class="{{ $campo }} uppercase {{ $errors->has('curp') ? 'border-red-500' : 'border-gray-600' }}"
                aria-describedby="curp-ayuda @if ($errors->has('curp')) curp-error @endif"
                @if ($errors->has('curp')) aria-invalid="true" @endif
            >
        </x-form.field>

        <x-form.field name="nombre" label="Nombre" required :error="$errors->first('nombre')">
            <input
                type="text"
                id="nombre"
                name="nombre"
                maxlength="255"
                required
                value="{{ old('nombre') }}"
                class="{{ $campo }} {{ $errors->has('nombre') ? 'border-red-500' : 'border-gray-600' }}"
                @if ($errors->has('nombre')) aria-invalid="true" aria-describedby="nombre-error" @endif
            >
        </x-form.field>

        <x-form.field name="apellidos" label="Apellidos" required :error="$errors->first('apellidos')">
            <input
                type="text"
                id="apellidos"
                name="apellidos"
                maxlength="255"
                required
                value="{{ old('apellidos') }}"
                class="{{ $campo }} {{ $errors->has('apellidos') ? 'border-red-500' : 'border-gray-600' }}"
                @if ($errors->has('apellidos')) aria-invalid="true" aria-describedby="apellidos-error" @endif
            >
        </x-form.field>

        <x-form.field name="fecha_nacimiento" label="Fecha de nacimiento" required :error="$errors->first('fecha_nacimiento')">
            <input
                type="date"
                id="fecha_nacimiento"
                name="fecha_nacimiento"
                required
                value="{{ old('fecha_nacimiento') }}"
                class="{{ $campo }} sm:max-w-xs {{ $errors->has('fecha_nacimiento') ? 'border-red-500' : 'border-gray-600' }}"
                @if ($errors->has('fecha_nacimiento')) aria-invalid="true" aria-describedby="fecha_nacimiento-error" @endif
            >
        </x-form.field>

        @include('personas._rol')

        <p class="text-xs text-gray-400">
            <span class="text-red-400" aria-hidden="true">*</span> Campo obligatorio.
        </p>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                Registrar
            </button>
            <a href="{{ route('personas.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
