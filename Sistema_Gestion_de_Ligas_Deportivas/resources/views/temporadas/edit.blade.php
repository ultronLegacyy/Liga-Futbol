@extends('layouts.admin')

@section('title', 'Editar Temporada - SGLD')
@section('header', 'Editar Temporada Deportiva')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-4">Modificar Datos de la Temporada</h2>

    <form action="{{ isset($temporada) ? route('temporadas.update', $temporada) : '#' }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-300 mb-1">Nombre de la Temporada</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $temporada->nombre ?? '') }}" required class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-green-500">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="fecha_inicio" class="block text-sm font-medium text-gray-300 mb-1">Fecha de Inicio</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', $temporada->fecha_inicio ?? '') }}" required class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-green-500">
            </div>
            <div>
                <label for="fecha_fin" class="block text-sm font-medium text-gray-300 mb-1">Fecha de Fin</label>
                <input type="date" name="fecha_fin" id="fecha_fin" value="{{ old('fecha_fin', $temporada->fecha_fin ?? '') }}" required class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-green-500">
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <a href="{{ route('temporadas.index') }}" class="px-4 py-2 bg-gray-700 text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-600">
                Cancelar
            </a>
            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                Guardar Cambios
            </button>
        </div>
    </form>
</div>
@endsection