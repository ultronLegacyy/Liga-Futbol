@extends('layouts.admin')

@section('title', 'Liguilla de '.$grupo->nombre.' - SGLD')
@section('header', 'Liguilla')

@section('content')
{{-- HU-055: cruces 1° vs N°, 2° vs (N-1)°... desde la tabla final; cada llave conserva las reglas usadas (RNF-36) --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-4xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $grupo->categoria?->nombre }} · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">Temporada {{ $grupo->temporada?->nombre }}</p>
        </div>
        <a href="{{ route('grupos.tabla', $grupo) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Ver tabla de posiciones
        </a>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-semibold">No se generaron las llaves:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($llaves->isNotEmpty())
        @php($primera = $llaves->first())
        <p class="text-sm text-gray-300">
            Generadas por {{ $primera->generadaPor?->name ?? 'Sin dato' }} el {{ $primera->created_at?->format('d/m/Y H:i') }}
            con la versión {{ $primera->regla_version ?? '—' }} de las reglas de la temporada
            y {{ $primera->criterios_clasificacion['clasifican_liguilla'] ?? '—' }} equipos clasificados.
        </p>

        <x-table caption="Llaves de liguilla">
            <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                <tr>
                    <th scope="col" class="px-4 py-3">Llave</th>
                    <th scope="col" class="px-4 py-3">Equipo</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">contra</span></th>
                    <th scope="col" class="px-4 py-3">Equipo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @foreach ($llaves as $llave)
                    <tr data-llave="{{ $llave->orden }}">
                        <td class="px-4 py-3 text-white">{{ $llave->orden }}</td>
                        <td class="px-4 py-3"><span class="text-white">{{ $llave->local?->nombre }}</span> ({{ $llave->lugar_local }}°)</td>
                        <td class="px-4 py-3 text-gray-400">vs</td>
                        <td class="px-4 py-3"><span class="text-white">{{ $llave->visitante?->nombre }}</span> ({{ $llave->lugar_visitante }}°)</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @elseif ($revision['problemas'] !== [])
        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
            <p class="font-semibold">Todavía no se pueden generar las llaves:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($revision['problemas'] as $problema)
                    <li>{{ $problema }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <p class="text-sm text-gray-300">Vista previa con los {{ $revision['clasifican'] }} primeros lugares de la tabla final. Nada se guarda hasta confirmar.</p>

        <x-table caption="Cruces propuestos">
            <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                <tr>
                    <th scope="col" class="px-4 py-3">Llave</th>
                    <th scope="col" class="px-4 py-3">Equipo</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">contra</span></th>
                    <th scope="col" class="px-4 py-3">Equipo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @foreach ($revision['cruces'] as $cruce)
                    <tr>
                        <td class="px-4 py-3 text-white">{{ $cruce['orden'] }}</td>
                        <td class="px-4 py-3"><span class="text-white">{{ $cruce['local']->equipo?->nombre }}</span> ({{ $cruce['local']->posicion }}°)</td>
                        <td class="px-4 py-3 text-gray-400">vs</td>
                        <td class="px-4 py-3"><span class="text-white">{{ $cruce['visitante']->equipo?->nombre }}</span> ({{ $cruce['visitante']->posicion }}°)</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        @if ($puedeGenerar)
            <form method="POST" action="{{ route('grupos.liguilla.store', $grupo) }}"
                onsubmit="return confirm('¿Generar las llaves de liguilla? Quedarán registradas con las reglas actuales.');">
                @csrf
                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400">
                    Generar llaves
                </button>
            </form>
        @endif
    @endif
</div>
@endsection
