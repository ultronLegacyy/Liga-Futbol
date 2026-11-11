@extends('layouts.admin')

@section('title', 'Permisos de '.$rol->name.' - SGLD')
@section('header', 'Roles y permisos')

@section('content')
{{-- CA2 y CA3 de HU-081: módulos y acciones del rol leídos del motor de permisos, sin controles para cambiarlos --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-4xl">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $rol->name }}</h2>
            <p class="text-sm text-gray-400">Solo consulta: en esta versión los permisos son fijos y no se pueden cambiar.</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver a roles
        </a>
    </div>

    <x-table caption="Módulos y acciones permitidas para {{ $rol->name }}">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3">Módulo</th>
                <th scope="col" class="px-4 py-3">Acciones permitidas</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
            @foreach ($modulos as $modulo)
                <tr>
                    <th scope="row" class="px-4 py-3 font-semibold text-white align-top">{{ $modulo['nombre'] }}</th>
                    <td class="px-4 py-3">
                        @if ($modulo['permitidas'] === 0)
                            <span class="text-gray-400">Sin acceso</span>
                        @else
                            <ul class="flex flex-wrap gap-2" aria-label="Acciones permitidas en {{ $modulo['nombre'] }}">
                                @foreach ($modulo['acciones'] as $accion)
                                    @if ($accion['permitida'])
                                        <li class="px-2 py-1 text-xs rounded bg-green-500/10 text-green-300 border border-green-500/30" data-permiso="{{ $accion['permiso'] }}">
                                            {{ $accion['nombre'] }}
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
</div>
@endsection
