@extends('layouts.admin')

@section('title', 'Personas - SGLD')
@section('header', 'Personas')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Padrón de personas</h2>
            <p class="text-sm text-gray-400">Cada persona se registra una sola vez y puede tener varios roles por temporada.</p>
        </div>

        @can('create', App\Models\Persona::class)
            <a href="{{ route('personas.create') }}" class="self-start px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                + Registrar persona
            </a>
        @endcan
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <x-table caption="Personas registradas y sus roles">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3">Nombre</th>
                <th scope="col" class="px-4 py-3">Roles por temporada</th>
                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
            @forelse ($personas as $persona)
                <tr>
                    <td class="px-4 py-3 font-semibold text-white">{{ $persona->apellidos }}, {{ $persona->nombre }}</td>
                    <td class="px-4 py-3">
                        @forelse ($persona->roles as $rol)
                            <span class="inline-block mb-1 px-2 py-1 text-xs rounded bg-gray-700 text-gray-200 border border-gray-600">
                                {{ App\Models\PersonaRol::ETIQUETAS[$rol->rol] }} · {{ $rol->temporada->nombre }}
                            </span>
                        @empty
                            <span class="text-gray-400">Sin roles</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('personas.show', $persona) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                            Ver<span class="sr-only"> {{ $persona->nombre }} {{ $persona->apellidos }}</span>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-6 text-center text-gray-400">
                        No hay personas registradas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $personas->links() }}
    </div>
</div>
@endsection
