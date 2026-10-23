@extends('layouts.admin')

@section('title', 'Editar '.$cancha->nombre.' - SGLD')
@section('header', 'Canchas y Sedes')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Editar cancha</h2>
    <p class="text-sm text-gray-400 mb-6">Los cambios se guardan en la auditoría.</p>

    @include('canchas._formulario', [
        'accion' => route('canchas.update', $cancha),
        'metodo' => 'PUT',
        'textoBoton' => 'Guardar cambios',
        'cancelar' => route('canchas.show', $cancha),
    ])
</div>
@endsection
