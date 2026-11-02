@extends('layouts.admin')

@section('title', $persona->nombre.' '.$persona->apellidos.' - SGLD')
@section('header', 'Personas')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl space-y-8">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $persona->nombre }} {{ $persona->apellidos }}</h2>
            <p class="text-sm text-gray-400">Identidad única con sus roles en cada temporada.</p>
        </div>
        <a href="{{ route('personas.index') }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver al padrón
        </a>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">CURP</dt>
            <dd class="mt-1 text-white font-mono">{{ $persona->curp }}</dd>
        </div>
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Fecha de nacimiento</dt>
            <dd class="mt-1 text-white">{{ $persona->fecha_nacimiento->format('d/m/Y') }}</dd>
        </div>
    </dl>

    <section aria-labelledby="titulo-roles">
        <h3 id="titulo-roles" class="text-lg font-semibold text-white mb-3">Roles por temporada</h3>

        @forelse ($rolesPorTemporada as $rolesDeTemporada)
            @php($temporada = $rolesDeTemporada->first()->temporada)
            <div class="mb-3 bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <p class="text-sm font-semibold text-white">
                    {{ $temporada->nombre }}
                    @if ($temporada->estado === 'cerrada')
                        <span class="ml-2 px-2 py-0.5 text-xs rounded bg-gray-700 text-gray-300 border border-gray-600">Cerrada · solo consulta</span>
                    @endif
                </p>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($rolesDeTemporada as $rol)
                        <li class="px-2 py-1 text-xs rounded bg-green-500/10 text-green-300 border border-green-500/30">
                            {{ App\Models\PersonaRol::ETIQUETAS[$rol->rol] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-gray-400">La persona aún no tiene roles.</p>
        @endforelse
    </section>

    @can('create', App\Models\Persona::class)
        <section aria-labelledby="titulo-asignar" class="border-t border-gray-700 pt-6">
            <h3 id="titulo-asignar" class="text-lg font-semibold text-white mb-1">Asignar otro rol</h3>
            <p class="text-sm text-gray-400 mb-4">Se usa la misma identidad; no se crea otra persona.</p>

            @if ($errors->any())
                <x-alert type="error" message="No se asignó el rol. Revisa los campos marcados." />
            @endif

            @if ($temporadas->isEmpty())
                <p class="text-sm text-gray-400">No hay temporadas abiertas para asignar roles.</p>
            @else
                <form method="POST" action="{{ route('personas.roles.store', $persona) }}" novalidate class="space-y-5">
                    @csrf

                    @include('personas._rol')

                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                        Asignar rol
                    </button>
                </form>
            @endif
        </section>
    @endcan
</div>
@endsection
