@extends('layouts.admin')

@section('title', 'Asignación de árbitros - SGLD')
@section('header', 'Árbitros')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Asignación de árbitros</h2>
            <p class="text-sm text-gray-400">Partidos programados o confirmados de la programación semanal.</p>
        </div>
        <a href="{{ route('arbitros.index') }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver al padrón
        </a>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <x-table caption="Partidos programados y su árbitro">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3">Fecha y hora</th>
                <th scope="col" class="px-4 py-3">Partido</th>
                <th scope="col" class="px-4 py-3">Cancha</th>
                <th scope="col" class="px-4 py-3">Árbitro</th>
                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
            @forelse ($partidos as $partido)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap">
                        {{ $partido->fecha_partido?->format('d/m/Y') ?? 'Sin fecha' }} · {{ $partido->hora_partido ? substr($partido->hora_partido, 0, 5) : 'Sin hora' }}
                    </td>
                    <td class="px-4 py-3 text-white">{{ $partido->local->nombre }} vs. {{ $partido->visitante->nombre }}</td>
                    <td class="px-4 py-3">{{ $partido->cancha->nombre ?? 'Sin cancha' }}</td>
                    <td class="px-4 py-3">
                        @if ($partido->arbitro)
                            {{ $partido->arbitro->persona->nombre }} {{ $partido->arbitro->persona->apellidos }}
                        @else
                            <span class="text-yellow-400">Pendiente</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('partidos.arbitro.edit', $partido) }}" class="text-green-400 hover:underline focus:outline-none focus:ring-2 focus:ring-green-400 rounded">
                            Asignar<span class="sr-only"> árbitro a {{ $partido->local->nombre }} vs. {{ $partido->visitante->nombre }}</span>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">No hay partidos programados.</td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="mt-4">
        {{ $partidos->links() }}
    </div>
</div>
@endsection
