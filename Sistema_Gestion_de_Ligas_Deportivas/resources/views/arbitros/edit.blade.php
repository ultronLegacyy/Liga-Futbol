@extends('layouts.admin')

@section('title', 'Editar árbitro - SGLD')
@section('header', 'Árbitros')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">{{ $arbitro->persona->nombre }} {{ $arbitro->persona->apellidos }}</h2>
    <p class="text-sm text-gray-400 mb-6">Colegio, tarifa y disponibilidad del árbitro.</p>

    @if ($errors->any())
        <x-alert type="error" message="No se guardó el árbitro. Revisa los campos marcados." />
    @endif

    <form method="POST" action="{{ route('arbitros.update', $arbitro) }}" novalidate class="space-y-5">
        @csrf
        @method('PUT')

        @include('arbitros._formulario')

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                Guardar cambios
            </button>
            <a href="{{ route('arbitros.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
