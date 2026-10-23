@extends('layouts.admin')

@php
    $temporada = $categoria->temporada;
@endphp

@section('title', $categoria->nombre.' - SGLD')
@section('header', 'Categorías y Grupos')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $categoria->nombre }}</h2>
            <p class="text-sm text-gray-400">{{ $temporada->nombre }} · Rama {{ $categoria->rama ?? 'sin registrar' }}</p>
        </div>
        <a href="{{ route('temporadas.categorias.index', $temporada) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver a las categorías
        </a>
    </div>

    @include('categorias._cerrada')
    @include('categorias._exito')

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Años de nacimiento</dt>
            <dd class="mt-1 text-white">@include('categorias._rango')</dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Edad mínima</dt>
            <dd class="mt-1 text-white">{{ $categoria->edad_min !== null ? $categoria->edad_min.' años' : 'Sin edad mínima' }}</dd>
        </div>
    </dl>

    @can('update', $categoria)
        <div class="mt-6">
            <a href="{{ route('categorias.edit', $categoria) }}" class="inline-block px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
                Editar categoría
            </a>
        </div>
    @endcan

    <section class="border-t border-gray-700 mt-6 pt-6" aria-labelledby="grupos-titulo">
        <h3 id="grupos-titulo" class="text-lg font-semibold text-white mb-3">Grupos</h3>

        <ul class="divide-y divide-gray-700 border border-gray-700 rounded-lg text-sm">
            @forelse ($categoria->grupos as $grupo)
                <li class="flex justify-between items-center gap-4 px-4 py-3">
                    <span class="text-white">{{ $grupo->nombre }}</span>
                    @can('update', $grupo)
                        <a href="{{ route('grupos.edit', $grupo) }}" class="text-yellow-400 hover:underline focus:outline-none focus:ring-2 focus:ring-yellow-400 rounded">
                            Renombrar<span class="sr-only"> {{ $grupo->nombre }}</span>
                        </a>
                    @endcan
                </li>
            @empty
                <li class="px-4 py-4 text-gray-400">Esta categoría no tiene grupos.</li>
            @endforelse
        </ul>

        @can('create', [App\Models\Grupo::class, $categoria])
            {{-- Alta de grupos uno tras otro: al guardar se regresa a esta ficha (RF-02.2) --}}
            <form method="POST" action="{{ route('categorias.grupos.store', $categoria) }}" novalidate class="mt-4">
                @csrf
                <label for="nombre" class="block text-sm font-medium text-gray-200 mb-1">
                    Agregar grupo <span class="text-red-400" aria-hidden="true">*</span>
                </label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" id="nombre" name="nombre" maxlength="255" required
                        value="{{ old('nombre') }}" placeholder="Por ejemplo, Grupo A"
                        class="w-full sm:max-w-sm rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400 {{ $errors->has('nombre') ? 'border-red-500' : 'border-gray-600' }}"
                        @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>
                    <button type="submit" class="self-start px-4 py-3 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                        Agregar grupo
                    </button>
                </div>
                @error('nombre')
                    <p id="nombre-error" role="alert" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </form>
        @endcan
    </section>
</div>
@endsection
