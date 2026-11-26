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

{{-- HU-082 (criterio 4): si la persona perdió su celular y sus códigos, el Administrador General reinicia su segundo factor --}}
@php($erroresFactor = $errors->segundoFactor)
<section class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl mt-6" aria-labelledby="titulo-segundo-factor">
    <h2 id="titulo-segundo-factor" class="text-lg font-bold text-white mb-1">Segundo factor de autenticación</h2>
    <p class="text-sm text-gray-300 mb-4">
        Estado: {{ $usuario->hasEnabledTwoFactorAuthentication() ? 'activado' : ($usuario->two_factor_secret ? 'pendiente de confirmar' : 'sin activar') }}.
        {{ $usuario->requiereSegundoFactor() ? 'Su rol lo exige.' : 'Su rol no lo exige.' }}
    </p>

    @if (session('success'))
        <div role="status" class="mb-4 px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-300 text-sm">{{ session('success') }}</div>
    @endif

    @if ($usuario->is(auth()->user()))
        <p class="text-sm text-gray-400">No puedes reiniciar el segundo factor de tu propia cuenta.</p>
    @elseif ($usuario->two_factor_secret)
        <form method="POST" action="{{ route('admin.usuarios.segundo-factor.reiniciar', $usuario) }}" class="space-y-4" novalidate>
            @csrf
            <p class="text-sm text-gray-400">La cuenta deberá activarlo de nuevo, con su app de autenticación, la próxima vez que entre.</p>
            <div>
                <label for="motivo_segundo_factor" class="block text-sm font-medium text-gray-200 mb-1">Motivo</label>
                <input id="motivo_segundo_factor" name="motivo" type="text" required maxlength="255" value="{{ is_scalar(old('motivo')) && $erroresFactor->any() ? old('motivo') : '' }}"
                    placeholder="Por ejemplo, perdió su celular y sus códigos de recuperación"
                    class="w-full rounded-lg bg-gray-900 border text-white p-2.5 focus:outline-none focus:ring-2 focus:ring-green-400 {{ $erroresFactor->has('motivo') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($erroresFactor->has('motivo')) aria-invalid="true" aria-describedby="motivo-segundo-factor-error" @endif>
                @if ($erroresFactor->has('motivo'))
                    <p id="motivo-segundo-factor-error" class="mt-1 text-sm text-red-400">{{ $erroresFactor->first('motivo') }}</p>
                @endif
            </div>
            <div>
                <label for="password_segundo_factor" class="block text-sm font-medium text-gray-200 mb-1">Tu contraseña</label>
                <input id="password_segundo_factor" name="password" type="password" required autocomplete="current-password" maxlength="64"
                    class="w-full rounded-lg bg-gray-900 border text-white p-2.5 focus:outline-none focus:ring-2 focus:ring-green-400 {{ $erroresFactor->has('password') ? 'border-red-500' : 'border-gray-600' }}"
                    @if ($erroresFactor->has('password')) aria-invalid="true" aria-describedby="password-segundo-factor-error" @endif>
                @if ($erroresFactor->has('password'))
                    <p id="password-segundo-factor-error" class="mt-1 text-sm text-red-400">{{ $erroresFactor->first('password') }}</p>
                @endif
            </div>
            <button type="submit" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400">Reiniciar segundo factor</button>
        </form>
    @else
        <p class="text-sm text-gray-400">No hay nada que reiniciar.</p>
    @endif
</section>
@endsection
