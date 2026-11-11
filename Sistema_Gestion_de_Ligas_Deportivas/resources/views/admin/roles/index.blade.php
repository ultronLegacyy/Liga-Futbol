@extends('layouts.admin')

@section('title', 'Roles y permisos - SGLD')
@section('header', 'Roles y permisos')

@section('content')
{{-- CA1 de HU-081: los 13 roles con cuenta de la sección 2.3, sin «Público General» --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white">Roles y permisos</h2>
        <p class="text-sm text-gray-400">Solo consulta: en esta versión los permisos de cada rol son fijos.</p>
    </div>

    <x-table caption="Roles con cuenta en el sistema">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3">Rol</th>
                <th scope="col" class="px-4 py-3">Permisos</th>
                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
            @forelse ($roles as $rol)
                <tr>
                    <td class="px-4 py-3 font-semibold text-white">{{ $rol->name }}</td>
                    <td class="px-4 py-3">{{ $rol->permissions_count }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.roles.show', $rol->id) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                            Ver permisos<span class="sr-only"> de {{ $rol->name }}</span>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-6 text-center text-gray-400">
                        No hay roles registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-table>
</div>
@endsection
