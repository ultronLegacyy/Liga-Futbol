@extends('layouts.admin')

@section('title', 'Cuentas de usuario - SGLD')
@section('header', 'Cuentas de Usuario')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Cuentas de usuario</h2>
            <p class="text-sm text-gray-400">Alta, edición, roles y estado de las cuentas. Tu propia cuenta no aparece en este listado.</p>
        </div>
        @can('create', App\Models\User::class)
            <a href="{{ route('admin.usuarios.create') }}" class="self-start px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                + Nueva cuenta
            </a>
        @endcan
    </div>

    @if (session('success'))
        <p role="status" class="mb-4 px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 text-sm">
            {{ session('success') }}
        </p>
    @endif

    {{-- La contraseña temporal solo existe en este mensaje: no se guarda en claro ni se vuelve a mostrar (CA2, PA-16) --}}
    @if (session('password_temporal'))
        <div role="alert" class="mb-6 px-4 py-4 rounded-lg bg-yellow-500/10 border border-yellow-500/40 text-sm">
            <p class="font-semibold text-yellow-300">Contraseña temporal de {{ session('password_temporal')['email'] }}</p>
            <p class="mt-2">
                <code class="inline-block px-3 py-2 rounded bg-gray-900 border border-gray-600 text-white text-base tracking-wider select-all">{{ session('password_temporal')['password'] }}</code>
            </p>
            <p class="mt-2 text-yellow-200">Cópiala y entrégala a la persona ahora: no se volverá a mostrar. Al iniciar sesión por primera vez, el sistema le pedirá cambiarla.</p>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-300">
            <caption class="sr-only">Cuentas de usuario del sistema</caption>
            <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                <tr>
                    <th scope="col" class="px-4 py-3">Nombre</th>
                    <th scope="col" class="px-4 py-3">Correo electrónico</th>
                    <th scope="col" class="px-4 py-3">Roles</th>
                    <th scope="col" class="px-4 py-3">Estado</th>
                    <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @forelse ($usuarios as $usuario)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-white">{{ $usuario->name }}</td>
                        <td class="px-4 py-3">{{ $usuario->email }}</td>
                        <td class="px-4 py-3">{{ $usuario->roles->pluck('name')->sort()->join(', ') ?: 'Sin roles' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded border {{ $usuario->estatus === 'activo' ? 'bg-green-500/10 text-green-300 border-green-500/30' : 'bg-gray-700 text-gray-300 border-gray-600' }}">
                                {{ ucfirst($usuario->estatus) }}
                            </span>
                            @if ($usuario->requires_password_change)
                                <span class="block mt-1 text-xs text-yellow-300">Debe cambiar su contraseña</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            @can('update', $usuario)
                                <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="text-yellow-400 hover:underline focus:outline-none focus:ring-2 focus:ring-yellow-400 rounded">
                                    Editar<span class="sr-only"> {{ $usuario->name }}</span>
                                </a>
                            @endcan
                            @can('toggleStatus', $usuario)
                                <a href="{{ route('admin.usuarios.estado', $usuario) }}" class="{{ $usuario->estatus === 'activo' ? 'text-red-400 focus:ring-red-400' : 'text-green-400 focus:ring-green-400' }} hover:underline focus:outline-none focus:ring-2 rounded">
                                    {{ $usuario->estatus === 'activo' ? 'Desactivar' : 'Reactivar' }}<span class="sr-only"> {{ $usuario->name }}</span>
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-400">No hay otras cuentas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
