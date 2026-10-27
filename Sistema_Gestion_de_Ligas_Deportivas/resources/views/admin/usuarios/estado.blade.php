@extends('layouts.admin')

@php
    $desactivar = $usuario->estatus === 'activo';
@endphp

@section('title', ($desactivar ? 'Desactivar ' : 'Reactivar ').$usuario->name.' - SGLD')
@section('header', 'Cuentas de Usuario')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">{{ $desactivar ? 'Desactivar cuenta' : 'Reactivar cuenta' }}</h2>
    <p class="text-sm text-gray-400 mb-6">
        {{ $usuario->name }} · {{ $usuario->email }}.
        {{ $desactivar
            ? 'La cuenta no podrá iniciar sesión, pero se conserva con todos sus datos.'
            : 'La cuenta podrá iniciar sesión de nuevo con su contraseña actual.' }}
    </p>

    <form method="POST" action="{{ route('admin.usuarios.toggleStatus', $usuario) }}" novalidate class="space-y-5">
        @csrf
        @method('PATCH')

        <div>
            <label for="motivo" class="block text-sm font-medium text-gray-200 mb-1">
                Motivo <span class="text-red-400" aria-hidden="true">*</span>
            </label>
            <input type="text" id="motivo" name="motivo" maxlength="255" required autofocus
                value="{{ old('motivo') }}"
                class="w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400 {{ $errors->has('motivo') ? 'border-red-500' : 'border-gray-600' }}"
                aria-describedby="motivo-ayuda @error('motivo') motivo-error @enderror"
                @error('motivo') aria-invalid="true" @enderror>
            <p id="motivo-ayuda" class="mt-1 text-xs text-gray-400">Queda en la auditoría junto con tu usuario y la fecha.</p>
            @error('motivo')
                <p id="motivo-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 transition {{ $desactivar ? 'bg-red-600 hover:bg-red-700 focus:ring-red-400' : 'bg-green-600 hover:bg-green-700 focus:ring-green-400' }}">
                {{ $desactivar ? 'Desactivar cuenta' : 'Reactivar cuenta' }}
            </button>
            <a href="{{ route('admin.usuarios.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection