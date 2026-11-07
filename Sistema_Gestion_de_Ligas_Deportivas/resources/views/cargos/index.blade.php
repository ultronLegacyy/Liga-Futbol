@extends('layouts.admin')

@section('title', 'Cuotas y cargos - SGLD')
@section('header', 'Cuotas y cargos')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-2.5 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Cuotas de inscripción y cargos</h2>
            <p class="text-sm text-gray-400">Cada cargo queda ligado a su temporada y al club, equipo o jugador que lo generó.</p>
        </div>

        @can('create', App\Models\CargoFinanciero::class)
            <a href="{{ route('cargos.create') }}" class="self-start px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                + Registrar cargo
            </a>
        @endcan
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <form method="GET" action="{{ route('cargos.index') }}" class="grid gap-4 sm:grid-cols-4 items-end mb-6" aria-label="Filtrar cargos">
        <x-form.field name="filtro_temporada" label="Temporada">
            <select id="filtro_temporada" name="temporada_id" class="{{ $campo }}">
                <option value="">Todas</option>
                @foreach ($temporadas as $temporada)
                    <option value="{{ $temporada->id }}" @selected($filtros['temporada_id'] === $temporada->id)>{{ $temporada->nombre }}</option>
                @endforeach
            </select>
        </x-form.field>

        <x-form.field name="filtro_club" label="Club">
            <select id="filtro_club" name="club_id" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach ($clubes as $club)
                    <option value="{{ $club->id }}" @selected($filtros['club_id'] === $club->id)>{{ $club->nombre }}</option>
                @endforeach
            </select>
        </x-form.field>

        <x-form.field name="filtro_concepto" label="Concepto">
            <select id="filtro_concepto" name="concepto" class="{{ $campo }}">
                <option value="">Todos</option>
                @foreach (App\Models\CargoFinanciero::CONCEPTOS as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($filtros['concepto'] === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </x-form.field>

        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2.5 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400">Filtrar</button>
            <a href="{{ route('cargos.index') }}" class="px-4 py-2.5 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">Limpiar</a>
        </div>
    </form>

    <x-table caption="Cuotas y cargos registrados">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3">Temporada</th>
                <th scope="col" class="px-4 py-3">Club</th>
                <th scope="col" class="px-4 py-3">Equipo</th>
                <th scope="col" class="px-4 py-3">Jugador</th>
                <th scope="col" class="px-4 py-3">Concepto</th>
                <th scope="col" class="px-4 py-3 text-right">Monto</th>
                <th scope="col" class="px-4 py-3">Vencimiento</th>
                <th scope="col" class="px-4 py-3">Estado</th>
                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
            @forelse ($cargos as $cargo)
                <tr>
                    <td class="px-4 py-3">{{ $cargo->temporada?->nombre ?? 'Sin temporada' }}</td>
                    <td class="px-4 py-3 font-semibold text-white">{{ $cargo->club->nombre }}</td>
                    <td class="px-4 py-3">{{ $cargo->equipo?->nombre ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $cargo->persona ? $cargo->persona->apellidos.', '.$cargo->persona->nombre : '—' }}</td>
                    <td class="px-4 py-3">{{ App\Models\CargoFinanciero::CONCEPTOS[$cargo->concepto] ?? $cargo->concepto }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">${{ number_format((float) $cargo->monto, 2) }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $cargo->vencimiento->format('d/m/Y') }}</td>
                    <td class="px-4 py-3">{{ App\Models\CargoFinanciero::ESTADOS[$cargo->estado] ?? $cargo->estado }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('cargos.show', $cargo) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                            Ver<span class="sr-only"> cargo {{ $cargo->id }}</span>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-6 text-center text-gray-400">No hay cargos con esos filtros.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $cargos->links() }}
    </div>
</div>
@endsection
