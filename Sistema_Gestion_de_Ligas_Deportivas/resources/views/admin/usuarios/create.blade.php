@extends('layouts.admin')

@section('title', 'Nueva cuenta - SGLD')
@section('header', 'Cuentas de Usuario')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Registrar cuenta</h2>
    <p class="text-sm text-gray-400 mb-6">La cuenta queda activa con una contraseña temporal que el sistema genera y te muestra una sola vez. La persona la cambia al entrar por primera vez.</p>

    @include('admin.usuarios._formulario', [
        'accion' => route('admin.usuarios.store'),
        'metodo' => 'POST',
        'textoBoton' => 'Registrar cuenta',
        'ejemploMotivo' => 'Por ejemplo, alta del nuevo tesorero',
    ])
</div>
@endsection
