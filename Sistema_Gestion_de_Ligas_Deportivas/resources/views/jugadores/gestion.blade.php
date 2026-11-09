@extends('layouts.admin')

@section('title', 'Ficha del jugador - SGLD')
@section('header', 'Ficha del jugador')

@section('content')
<main class="mx-auto max-w-5xl space-y-6">
    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7" aria-labelledby="jugador-titulo">
        <h2 id="jugador-titulo" class="text-xl font-bold text-white">{{ $jugador->persona->nombre }} {{ $jugador->persona->apellidos }}</h2>
        <p class="mt-1 text-sm text-gray-300">{{ $jugador->equipo->nombre }} · {{ $jugador->equipo->grupo->categoria->nombre }} · {{ $jugador->equipo->temporada->nombre }}</p>
        <p class="mt-4 text-sm text-gray-200">Estado actual: <span class="font-semibold">{{ $jugador->estado_elegibilidad }}</span></p>

        @can('jugadores.crear')
            <a href="{{ route('jugadores.create') }}" class="mt-5 inline-flex rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 hover:bg-gray-700">Registrar otro jugador</a>
        @endcan
    </section>

    <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7" aria-labelledby="documentos-titulo">
        <h2 id="documentos-titulo" class="text-lg font-semibold text-white">Documentos</h2>
        <ul class="mt-3 space-y-2">
            @forelse ($jugador->persona->documentos as $documento)
                <li><a class="text-emerald-300 underline" href="{{ route('jugadores.documentos.show', [$jugador, $documento]) }}">Descargar {{ $documento->tipo }}</a></li>
            @empty
                <li class="text-sm text-gray-300">No hay documentos registrados.</li>
            @endforelse
        </ul>
    </section>
</main>
@endsection