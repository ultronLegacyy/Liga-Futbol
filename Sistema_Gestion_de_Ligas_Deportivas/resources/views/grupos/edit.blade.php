@extends('layouts.admin')

@section('title', 'Renombrar '.$grupo->nombre.' - SGLD')
@section('header', 'Categorías y Grupos')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Renombrar grupo</h2>
    <p class="text-sm text-gray-400 mb-6">{{ $grupo->categoria->nombre }} · {{ $grupo->categoria->temporada->nombre }}</p>

    @if ($errors->any())
        <div role="alert" class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
            No se guardó el grupo. Revisa el nombre.
        </div>
    @endif

    <form method="POST" action="{{ route('grupos.update', $grupo) }}" novalidate class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-200 mb-1">
                Nombre <span class="text-red-400" aria-hidden="true">*</span>
            </label>
            <input type="text" id="nombre" name="nombre" maxlength="255" required autofocus
                value="{{ old('nombre', $grupo->nombre) }}"
                class="w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400 {{ $errors->has('nombre') ? 'border-red-500' : 'border-gray-600' }}"
                @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>
            @error('nombre')
                <p id="nombre-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                Guardar nombre
            </button>
            <a href="{{ route('categorias.show', $grupo->categoria) }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
