@extends('layouts.admin')

@section('title', 'Editar '.$usuario->name.' - SGLD')
@section('header', 'Cuentas de Usuario')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Editar cuenta</h2>
    <p class="text-sm text-gray-400 mb-6">La cuenta verá solo los módulos y acciones de los roles que queden marcados.</p>

    @include('admin.usuarios._formulario', [
        'accion' => route('admin.usuarios.update', $usuario),
        'metodo' => 'PUT',
        'textoBoton' => 'Guardar cambios',
        'ejemploMotivo' => 'Por ejemplo, cambio de funciones en el consejo',
    ])
</div>
@endsection