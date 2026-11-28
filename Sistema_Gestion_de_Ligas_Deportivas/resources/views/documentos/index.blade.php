@extends('layouts.admin')

@section('title', 'Documentos oficiales - SGLD')
@section('header', 'Documentos oficiales')

{{-- HU-069: punto de entrada del Tesorero y el Presidente para descargar los PDF oficiales --}}
@section('content')
<div class="space-y-6">
    @if ($errors->any())
        <x-alert type="error" :message="$errors->first()" />
    @endif

    <section class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6" aria-labelledby="titulo-cedulas">
        <h2 id="titulo-cedulas" class="text-xl font-bold text-white">Cédulas oficiales</h2>
        <p class="mb-4 text-sm text-gray-400">Partidos con resultado validado. Solo estos generan la cédula oficial.</p>
        @if ($partidos->isEmpty())
            <p class="text-gray-300">Todavía no hay partidos con resultado validado.</p>
        @else
            <ul class="divide-y divide-gray-700">
                @foreach ($partidos as $partido)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <span class="text-white">
                            {{ $partido->local?->nombre }} {{ $partido->cedula?->goles_local }} – {{ $partido->cedula?->goles_visitante }} {{ $partido->visitante?->nombre }}
                            <span class="block text-sm text-gray-400">{{ $partido->fecha_partido ? \Carbon\Carbon::parse($partido->fecha_partido)->format('d/m/Y') : 'Sin fecha' }}</span>
                        </span>
                        <a href="{{ route('documentos.cedula', $partido) }}" class="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-500">Cédula oficial (PDF)</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6" aria-labelledby="titulo-clubes">
        <h2 id="titulo-clubes" class="text-xl font-bold text-white">Estados de cuenta por club</h2>
        <p class="mb-4 text-sm text-gray-400">Cargos y pagos de todas las temporadas.</p>
        @if ($clubes->isEmpty())
            <p class="text-gray-300">No hay clubes registrados.</p>
        @else
            <ul class="grid gap-2 sm:grid-cols-2">
                @foreach ($clubes as $club)
                    <li><a href="{{ route('documentos.estado-cuenta', $club) }}" class="flex min-h-11 items-center rounded-lg border border-gray-700 px-4 py-2 text-white hover:border-blue-400">{{ $club->nombre }} · estado de cuenta (PDF)</a></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6" aria-labelledby="titulo-constancias">
        <h2 id="titulo-constancias" class="text-xl font-bold text-white">Constancias de registro</h2>
        <form method="GET" action="{{ route('documentos.index') }}" class="mt-4 flex flex-wrap items-end gap-3" role="search">
            <div class="grow">
                <label for="jugador" class="mb-1 block text-sm text-gray-300">Nombre o apellidos del jugador</label>
                <input id="jugador" name="jugador" type="search" value="{{ $buscar }}" minlength="2" maxlength="60" class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-2.5">
            </div>
            <button type="submit" class="min-h-11 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-500">Buscar</button>
        </form>
        @if ($buscar !== '')
            @if ($jugadores->isEmpty())
                <p class="mt-4 text-gray-300">No hay jugadores con ese nombre.</p>
            @else
                <ul class="mt-4 divide-y divide-gray-700">
                    @foreach ($jugadores as $jugador)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <span class="text-white">{{ trim(($jugador->persona->nombre ?? '').' '.($jugador->persona->apellidos ?? '')) }}<span class="block text-sm text-gray-400">{{ $jugador->equipo?->nombre }}</span></span>
                            <a href="{{ route('documentos.constancia', $jugador) }}" class="inline-flex min-h-11 items-center rounded-lg border border-gray-600 px-4 py-2 text-sm font-bold text-white hover:border-blue-400">Constancia (PDF)</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>
</div>
@endsection
