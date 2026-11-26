@extends('layouts.admin')

@section('title', 'Corregir resultado - SGLD')
@section('header', 'Resultados')

@section('content')
{{-- HU-039: corregir un resultado ya validado; el motivo queda en la bitácora y la tabla se recalcula --}}
@php
    $partido = $cedula->partido;
    $campo = 'w-24 rounded-lg bg-gray-900 border border-gray-600 text-white p-2 text-center focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-3xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Corregir resultado</h2>
            <p class="text-sm text-gray-400">
                {{ $partido?->jornada?->grupo?->categoria?->nombre }} · {{ $partido?->jornada?->grupo?->nombre }} · Jornada {{ $partido?->jornada?->numero_jornada }}
            </p>
        </div>
        <a href="{{ route('resultados.index') }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver a resultados
        </a>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-semibold">No se corrigió el resultado:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-sm text-gray-300">
        Resultado oficial: <span class="font-mono text-white">{{ $partido?->local?->nombre }} {{ $cedula->goles_local }} - {{ $cedula->goles_visitante }} {{ $partido?->visitante?->nombre }}</span>
        <x-badge variant="success">Oficial</x-badge>
        <span class="block text-xs text-gray-400">Validó {{ $cedula->validadaPor?->name ?? 'sin dato' }} el {{ $cedula->validada_en?->format('d/m/Y H:i') }}.</span>
    </p>

    <form method="POST" action="{{ route('resultados.corregir.update', $cedula->id) }}" class="space-y-4"
        onsubmit="return confirm('¿Corregir el resultado? Quedará en la bitácora y se recalculará la tabla de posiciones.');">
        @csrf
        @method('PUT')

        <fieldset class="flex flex-wrap items-end gap-4">
            <legend class="mb-2 text-sm font-medium text-gray-200">Marcador corregido</legend>
            <div>
                <label for="goles_local" class="block mb-1 text-sm text-gray-300">{{ $partido?->local?->nombre }}</label>
                <input id="goles_local" name="goles_local" type="number" min="0" max="99" required
                    value="{{ is_scalar(old('goles_local')) ? old('goles_local') : $cedula->goles_local }}" class="{{ $campo }}">
            </div>
            <span class="pb-2 text-gray-400">-</span>
            <div>
                <label for="goles_visitante" class="block mb-1 text-sm text-gray-300">{{ $partido?->visitante?->nombre }}</label>
                <input id="goles_visitante" name="goles_visitante" type="number" min="0" max="99" required
                    value="{{ is_scalar(old('goles_visitante')) ? old('goles_visitante') : $cedula->goles_visitante }}" class="{{ $campo }}">
            </div>
        </fieldset>

        <div>
            <label for="motivo" class="block mb-1 text-sm font-medium text-gray-200">Motivo de la corrección</label>
            <textarea id="motivo" name="motivo" rows="3" required minlength="10" maxlength="1000"
                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400">{{ is_string(old('motivo')) ? old('motivo') : '' }}</textarea>
            <p class="mt-1 text-xs text-gray-400">Obligatorio. Queda en la bitácora con tu usuario, la fecha y hora, la IP y el marcador anterior y el nuevo.</p>
        </div>

        <button type="submit" class="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-400">
            Guardar corrección
        </button>
    </form>

    <section aria-labelledby="titulo-correcciones" class="space-y-3">
        <h3 id="titulo-correcciones" class="text-lg font-semibold text-white">Correcciones anteriores</h3>
        @if ($historial->isEmpty())
            <p class="text-sm text-gray-400">Este resultado no se ha corregido.</p>
        @else
            <x-table caption="Correcciones del resultado">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Fecha</th>
                        <th scope="col" class="px-4 py-3">Usuario</th>
                        <th scope="col" class="px-4 py-3">Marcador</th>
                        <th scope="col" class="px-4 py-3">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($historial as $registro)
                        @php
                            $antes = is_array($registro->valores_anteriores) ? $registro->valores_anteriores : [];
                            $despues = is_array($registro->valores_nuevos) ? $registro->valores_nuevos : [];
                        @endphp
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $registro->created_at?->timezone(\App\Services\GestionJugadorService::ZONA_HORARIA)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $registro->usuario?->name ?? 'Sistema' }}</td>
                            <td class="px-4 py-3 font-mono">
                                {{ $antes['goles_local'] ?? $cedula->goles_local }} - {{ $antes['goles_visitante'] ?? $cedula->goles_visitante }}
                                →
                                {{ $despues['goles_local'] ?? $cedula->goles_local }} - {{ $despues['goles_visitante'] ?? $cedula->goles_visitante }}
                            </td>
                            <td class="px-4 py-3">{{ $registro->motivo }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </section>
</div>
@endsection
