@extends('layouts.admin')

@section('title', 'Detalle del cargo - SGLD')
@section('header', 'Cuotas y cargos')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">
        {{ App\Models\CargoFinanciero::CONCEPTOS[$cargo->concepto] ?? $cargo->concepto }} · {{ $cargo->club->nombre }}
    </h2>
    <p class="text-sm text-gray-400 mb-6">Cargo {{ $cargo->id }}, registrado el {{ $cargo->created_at?->format('d/m/Y') }}.</p>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if ($cargo->temporada?->estado === 'cerrada')
        <x-alert type="info" message="La temporada está cerrada: este cargo solo se consulta." />
    @endif

    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
        <div>
            <dt class="text-gray-400">Temporada</dt>
            <dd class="text-white">{{ $cargo->temporada?->nombre ?? 'Sin temporada' }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Club</dt>
            <dd class="text-white">{{ $cargo->club->nombre }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Equipo</dt>
            <dd class="text-white">{{ $cargo->equipo?->nombre ?? 'Sin equipo' }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Jugador</dt>
            <dd class="text-white">{{ $cargo->persona ? $cargo->persona->apellidos.', '.$cargo->persona->nombre : 'Sin jugador' }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Monto</dt>
            <dd class="text-white">${{ number_format((float) $cargo->monto, 2) }} MXN</dd>
        </div>
        <div>
            <dt class="text-gray-400">Vencimiento</dt>
            <dd class="text-white">{{ $cargo->vencimiento->format('d/m/Y') }}</dd>
        </div>
        <div>
            <dt class="text-gray-400">Estado</dt>
            <dd class="text-white">{{ App\Models\CargoFinanciero::ESTADOS[$cargo->estado] ?? $cargo->estado }}</dd>
        </div>
    </dl>

    <p class="mt-6">
        <a href="{{ route('cargos.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">Volver al listado</a>
    </p>
</div>
@endsection
