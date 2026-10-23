@extends('layouts.admin')

@section('title', 'Nueva cancha - SGLD')
@section('header', 'Canchas y Sedes')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Registrar cancha</h2>
    <p class="text-sm text-gray-400 mb-6">La cancha queda disponible en el catálogo con su capacidad.</p>

    @include('canchas._formulario', [
        'accion' => route('canchas.store'),
        'metodo' => 'POST',
        'textoBoton' => 'Registrar cancha',
        'cancelar' => route('canchas.index'),
    ])
</div>
@endsection
