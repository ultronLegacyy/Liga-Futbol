@extends('layouts.admin')

@section('title', 'Detalle de Temporada - SGLD')
@section('header', 'Detalles de la Temporada')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-6 max-w-4xl">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-white">{{ $temporada->nombre ?? 'Detalle de Temporada' }}</h2>
        <a href="{{ route('temporadas.index') }}" class="text-sm text-gray-400 hover:text-white">
            ← Volver al listado
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
            <span class="text-xs text-gray-400 uppercase">Fecha de Inicio</span>
            <p class="text-lg font-semibold text-white">{{ $temporada->fecha_inicio ?? 'N/A' }}</p>
        </div>
        <div class="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
            <span class="text-xs text-gray-400 uppercase">Fecha de Finalización</span>
            <p class="text-lg font-semibold text-white">{{ $temporada->fecha_fin ?? 'N/A' }}</p>
        </div>
    </div>

    <div class="border-t border-gray-700 pt-6">
        <h3 class="text-lg font-semibold text-white mb-2">Convocatoria y Versionado</h3>
        <p class="text-sm text-gray-400 mb-4">Consulta la documentación de la temporada deportiva.</p>
        
        <div class="flex gap-3">
            @if(isset($temporada))
            <a href="{{ route('temporadas.convocatoria', $temporada) }}" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                Descargar Convocatoria
            </a>
            @endif
        </div>
    </div>
</div>
@endsection