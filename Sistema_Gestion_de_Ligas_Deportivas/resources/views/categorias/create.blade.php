@extends('layouts.admin')

@section('title', 'Nueva categoría - SGLD')
@section('header', 'Categorías y Grupos')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Registrar categoría</h2>
    <p class="text-sm text-gray-400 mb-6">La categoría queda en {{ $temporada->nombre }}. Después le agregas sus grupos.</p>

    @include('categorias._formulario', [
        'accion' => route('temporadas.categorias.store', $temporada),
        'metodo' => 'POST',
        'textoBoton' => 'Registrar categoría',
        'cancelar' => route('temporadas.categorias.index', $temporada),
    ])
</div>
@endsection
