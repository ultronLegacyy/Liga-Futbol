@extends('layouts.admin')

@section('title', $cancha->nombre.' - SGLD')
@section('header', 'Canchas y Sedes')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $cancha->nombre }}</h2>
            <p class="text-sm text-gray-400">Características y capacidad de la cancha.</p>
        </div>
        <a href="{{ route('canchas.index') }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver al catálogo
        </a>
    </div>

    @if (session('success'))
        <p role="status" class="mb-4 px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 text-sm">
            {{ session('success') }}
        </p>
    @endif

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Dirección o ubicación</dt>
            <dd class="mt-1 text-white">{{ $cancha->direccion_ubicacion ?? 'Sin dirección registrada' }}</dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Capacidad de partidos consecutivos</dt>
            <dd class="mt-1 text-white">{{ $cancha->capacidad_partidos_consecutivos }}</dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Estado</dt>
            {{-- El estado cambia con la disponibilidad y los bloqueos (HU-024), no desde este catálogo --}}
            <dd class="mt-1 text-white">{{ $cancha->estado }}</dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Última actualización</dt>
            <dd class="mt-1 text-white">{{ $cancha->updated_at?->format('d/m/Y H:i') }}</dd>
        </div>
    </dl>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        @can('view', $cancha)
            <a href="{{ route('canchas.bloqueos.index', $cancha) }}" class="inline-block px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                Disponibilidad y Bloqueos
            </a>
        @endcan
        @can('update', $cancha)
            <a href="{{ route('canchas.edit', $cancha) }}" class="inline-block px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
                Editar cancha
            </a>
        @endcan
    </div>
</div>
@endsection
