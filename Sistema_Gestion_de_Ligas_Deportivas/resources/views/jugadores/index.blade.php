@extends('layouts.admin')

@section('title', 'Jugadores - SGLD')
@section('header', 'Jugadores')

@section('content')
<main class="mx-auto max-w-6xl space-y-6">
    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7" aria-labelledby="jugadores-titulo">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="jugadores-titulo" class="text-xl font-bold text-white">Jugadores</h2>
            @can('jugadores.crear')
                <a href="{{ route('jugadores.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Registrar jugador</a>
            @endcan
        </div>

        <form method="GET" action="{{ route('jugadores.index') }}" class="mt-5 flex flex-wrap gap-3" role="search">
            <label for="buscar-jugador" class="sr-only">Buscar por nombre, apellidos o CURP</label>
            <input id="buscar-jugador" name="q" type="search" value="{{ $busqueda }}" maxlength="120" placeholder="Nombre, apellidos o CURP" class="w-full max-w-md rounded-lg border border-gray-600 bg-gray-900 p-2 text-white">
            <button type="submit" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 hover:bg-gray-700">Buscar</button>
        </form>

        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-200">
                <thead class="border-b border-gray-700 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="py-2 pr-4">Jugador</th>
                        <th scope="col" class="py-2 pr-4">Club</th>
                        <th scope="col" class="py-2 pr-4">Equipo</th>
                        <th scope="col" class="py-2 pr-4">Categoría</th>
                        <th scope="col" class="py-2 pr-4">Temporada</th>
                        <th scope="col" class="py-2 pr-4">Estado</th>
                        <th scope="col" class="py-2"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jugadores as $jugador)
                        <tr class="border-b border-gray-800">
                            <td class="py-2 pr-4">{{ $jugador->persona->nombre }} {{ $jugador->persona->apellidos }}</td>
                            <td class="py-2 pr-4">{{ $jugador->equipo->club?->nombre }}</td>
                            <td class="py-2 pr-4">{{ $jugador->equipo->nombre }}</td>
                            <td class="py-2 pr-4">{{ $jugador->equipo->grupo?->categoria?->nombre }}</td>
                            <td class="py-2 pr-4">{{ $jugador->equipo->temporada?->nombre }}</td>
                            <td class="py-2 pr-4">{{ $jugador->estado_elegibilidad }}</td>
                            <td class="py-2">
                                <a href="{{ route('jugadores.gestion', $jugador) }}" class="text-emerald-300 underline">Ver ficha<span class="sr-only"> de {{ $jugador->persona->nombre }} {{ $jugador->persona->apellidos }}</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 text-gray-300">No hay jugadores que coincidan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $jugadores->links() }}</div>
    </section>
</main>
@endsection
