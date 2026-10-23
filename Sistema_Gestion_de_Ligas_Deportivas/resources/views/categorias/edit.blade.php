@extends('layouts.admin')

@section('title', 'Editar '.$categoria->nombre.' - SGLD')
@section('header', 'Categorías y Grupos')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Editar categoría</h2>
    <p class="text-sm text-gray-400 mb-6">{{ $categoria->temporada->nombre }}. Los cambios se guardan en la auditoría.</p>

    @include('categorias._formulario', [
        'accion' => route('categorias.update', $categoria),
        'metodo' => 'PUT',
        'textoBoton' => 'Guardar cambios',
        'cancelar' => route('categorias.show', $categoria),
    ])
</div>
@endsection
