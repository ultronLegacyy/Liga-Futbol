@extends('layouts.admin')

@section('title', 'Asignar árbitro - SGLD')
@section('header', 'Árbitros')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-white">{{ $partido->local->nombre }} vs. {{ $partido->visitante->nombre }}</h2>
        <p class="text-sm text-gray-400">{{ $temporada->nombre }}</p>
    </div>

    {{-- Datos del partido que conserva la asignación (RF-06.1) --}}
    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Cancha</dt>
            <dd class="mt-1 text-white">{{ $partido->cancha->nombre ?? 'Sin cancha' }}</dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Fecha</dt>
            <dd class="mt-1 text-white">{{ $partido->fecha_partido?->format('d/m/Y') ?? 'Sin fecha' }}</dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Hora</dt>
            <dd class="mt-1 text-white">{{ $partido->hora_partido ? substr($partido->hora_partido, 0, 5) : 'Sin hora' }}</dd>
        </div>
    </dl>

    <p class="text-sm text-gray-300">
        Árbitro actual:
        <span class="text-white">{{ $partido->arbitro ? $partido->arbitro->persona->nombre.' '.$partido->arbitro->persona->apellidos : 'Pendiente' }}</span>
    </p>

    @if ($errors->any())
        <x-alert type="error" :message="$errors->first('arbitro_id')" />
    @endif

    @if ($disponibles->isEmpty())
        <p class="text-sm text-gray-400">No hay árbitros activos con rol de árbitro en esta temporada.</p>
    @else
        <form method="POST" action="{{ route('partidos.arbitro.update', $partido) }}" novalidate class="space-y-5">
            @csrf
            @method('PUT')

            <x-form.field name="arbitro_id" label="Árbitro" required help="Solo aparecen árbitros activos con rol de árbitro en la temporada del partido.">
                <select
                    id="arbitro_id"
                    name="arbitro_id"
                    required
                    class="{{ $campo }} {{ $errors->has('arbitro_id') ? 'border-red-500' : 'border-gray-600' }}"
                    aria-describedby="arbitro_id-ayuda"
                    @if ($errors->has('arbitro_id')) aria-invalid="true" @endif
                >
                    <option value="">Selecciona un árbitro</option>
                    @foreach ($disponibles as $arbitro)
                        <option value="{{ $arbitro->id }}" @selected((string) old('arbitro_id', $partido->arbitro_id) === (string) $arbitro->id)>{{ $arbitro->persona->apellidos }}, {{ $arbitro->persona->nombre }}</option>
                    @endforeach
                </select>
            </x-form.field>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                    Asignar árbitro
                </button>
                <a href="{{ route('arbitros.asignaciones') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                    Cancelar
                </a>
            </div>
        </form>
    @endif
</div>
@endsection
