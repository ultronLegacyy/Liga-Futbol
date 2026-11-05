@extends('layouts.admin')

@section('title', 'Registrar árbitro - SGLD')
@section('header', 'Árbitros')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Registrar árbitro en el padrón</h2>
    <p class="text-sm text-gray-400 mb-6">
        Se elige a una persona que ya tiene rol de árbitro; si no aparece, primero regístrala en
        <a href="{{ route('personas.create') }}" class="text-blue-400 hover:underline">Personas</a>.
    </p>

    @if ($errors->any())
        <x-alert type="error" message="No se registró el árbitro. Revisa los campos marcados." />
    @endif

    <form method="POST" action="{{ route('arbitros.store') }}" novalidate class="space-y-5">
        @csrf

        <x-form.field name="persona_id" label="Persona" required :error="$errors->first('persona_id')">
            <select
                id="persona_id"
                name="persona_id"
                required
                class="{{ $campo }} {{ $errors->has('persona_id') ? 'border-red-500' : 'border-gray-600' }}"
                @if ($errors->has('persona_id')) aria-invalid="true" aria-describedby="persona_id-error" @endif
            >
                <option value="">Selecciona a la persona</option>
                @foreach ($candidatas as $persona)
                    <option value="{{ $persona->id }}" @selected((string) old('persona_id') === (string) $persona->id)>{{ $persona->apellidos }}, {{ $persona->nombre }}</option>
                @endforeach
            </select>
        </x-form.field>

        @include('arbitros._formulario')

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                Registrar árbitro
            </button>
            <a href="{{ route('arbitros.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
