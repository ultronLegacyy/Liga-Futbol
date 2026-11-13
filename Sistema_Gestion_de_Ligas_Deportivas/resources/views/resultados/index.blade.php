@extends('layouts.admin')

@section('title', 'Resultados por validar - SGLD')
@section('header', 'Resultados')

@section('content')
{{-- HU-040: un resultado capturado no es oficial ni cuenta para la tabla hasta que se valida --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-5xl space-y-8">
    <div>
        <h2 class="text-xl font-bold text-white">Resultados por validar</h2>
        <p class="text-sm text-gray-400">Partidos con la cédula cerrada. Al validar, el resultado pasa a oficial y se recalcula la tabla de posiciones de su grupo.</p>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-semibold">No se validó el resultado:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section aria-labelledby="titulo-pendientes" class="space-y-3">
        <h3 id="titulo-pendientes" class="text-lg font-semibold text-white">Pendientes ({{ $pendientes->count() }})</h3>

        @if ($pendientes->isEmpty())
            <p class="text-sm text-gray-400">No hay resultados esperando validación.</p>
        @else
            <x-table caption="Resultados capturados, todavía no oficiales">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Categoría y grupo</th>
                        <th scope="col" class="px-4 py-3">Jornada</th>
                        <th scope="col" class="px-4 py-3">Partido</th>
                        <th scope="col" class="px-4 py-3">Marcador</th>
                        <th scope="col" class="px-4 py-3">Cédula cerrada</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Acción</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($pendientes as $cedula)
                        @php($partido = $cedula->partido)
                        <tr>
                            <td class="px-4 py-3">{{ $partido?->jornada?->grupo?->categoria?->nombre }} · {{ $partido?->jornada?->grupo?->nombre }}</td>
                            <td class="px-4 py-3">{{ $partido?->jornada?->numero_jornada }}</td>
                            <td class="px-4 py-3">{{ $partido?->local?->nombre }} vs {{ $partido?->visitante?->nombre }}</td>
                            <td class="px-4 py-3 font-mono text-white">{{ $cedula->goles_local }} - {{ $cedula->goles_visitante }}</td>
                            <td class="px-4 py-3">{{ $cedula->cerrada_en?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('resultados.validar', $cedula->id) }}"
                                    onsubmit="return confirm('¿Validar este resultado? Pasará a oficial y actualizará la tabla de posiciones.');">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400">
                                        Validar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </section>

    <section aria-labelledby="titulo-recientes" class="space-y-3">
        <h3 id="titulo-recientes" class="text-lg font-semibold text-white">Validados recientemente</h3>

        @if ($recientes->isEmpty())
            <p class="text-sm text-gray-400">Todavía no hay resultados oficiales.</p>
        @else
            <x-table caption="Resultados oficiales">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Categoría y grupo</th>
                        <th scope="col" class="px-4 py-3">Partido</th>
                        <th scope="col" class="px-4 py-3">Marcador</th>
                        <th scope="col" class="px-4 py-3">Validó</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($recientes as $cedula)
                        @php($partido = $cedula->partido)
                        <tr>
                            <td class="px-4 py-3">{{ $partido?->jornada?->grupo?->categoria?->nombre }} · {{ $partido?->jornada?->grupo?->nombre }}</td>
                            <td class="px-4 py-3">{{ $partido?->local?->nombre }} vs {{ $partido?->visitante?->nombre }}</td>
                            <td class="px-4 py-3 font-mono text-white">{{ $cedula->goles_local }} - {{ $cedula->goles_visitante }} <x-badge variant="success">Oficial</x-badge></td>
                            <td class="px-4 py-3">{{ $cedula->validadaPor?->name ?? 'Sin dato' }} · {{ $cedula->validada_en?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </section>
</div>
@endsection
