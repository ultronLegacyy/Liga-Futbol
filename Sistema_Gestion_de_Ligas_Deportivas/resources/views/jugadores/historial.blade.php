@extends('layouts.admin')

@section('title', 'Historial de cambios del jugador - SGLD')
@section('header', 'Ficha del jugador')

@section('content')
{{-- HU-022 CA2: cada cambio a los datos del jugador con usuario, fecha y motivo (bitácora inmutable de HU-004) --}}
<div class="mx-auto max-w-5xl space-y-6">
    <a href="{{ route('jugadores.gestion', $jugador) }}" class="inline-flex text-sm text-emerald-300 underline">← Volver a la ficha del jugador</a>

    <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7" aria-labelledby="historial-cambios-titulo">
        <h2 id="historial-cambios-titulo" class="text-xl font-bold text-white">Historial de cambios</h2>
        <p class="mt-1 text-sm text-gray-300">
            {{ $jugador->persona->nombre }} {{ $jugador->persona->apellidos }} · {{ $jugador->equipo->nombre }}.
            Del cambio más reciente al más antiguo. Este historial no se puede editar ni borrar.
        </p>

        @if ($registros->isEmpty())
            <p class="mt-4 text-sm text-gray-300">Aún no hay cambios registrados para este jugador.</p>
        @else
            <div class="mt-4">
                <x-table caption="Historial de cambios de {{ $jugador->persona->nombre }} {{ $jugador->persona->apellidos }}">
                    <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                        <tr>
                            <th scope="col" class="px-4 py-3">Fecha y hora</th>
                            <th scope="col" class="px-4 py-3">Usuario</th>
                            <th scope="col" class="px-4 py-3">Cambio</th>
                            <th scope="col" class="px-4 py-3">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach ($registros as $registro)
                            <tr class="align-top">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $registro->created_at?->timezone(\App\Services\GestionJugadorService::ZONA_HORARIA)->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3">{{ $registro->usuario?->name ?? 'Sistema' }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-white">
                                        {{ \App\Services\HistorialCambiosJugadorService::ACCIONES[$registro->accion] ?? $registro->accion }}
                                    </span>
                                    <span class="text-xs text-gray-400">· {{ \App\Services\HistorialCambiosJugadorService::SECCIONES[$registro->tabla_afectada] ?? $registro->tabla_afectada }}</span>
                                    @php($cambios = $historial->cambios($registro))
                                    @if ($cambios !== [])
                                        <ul class="mt-1 space-y-0.5 text-xs text-gray-300">
                                            @foreach ($cambios as $cambio)
                                                <li>{{ $cambio['campo'] }}: {{ $cambio['anterior'] }} → {{ $cambio['nuevo'] }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $registro->motivo ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </div>

            <div class="mt-4">{{ $registros->links() }}</div>
        @endif
    </section>
</div>
@endsection
