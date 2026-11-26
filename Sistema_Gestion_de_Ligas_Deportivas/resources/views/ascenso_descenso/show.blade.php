@extends('layouts.admin')

@section('title', 'Ascenso y descenso de '.$grupo->nombre.' - SGLD')
@section('header', 'Ascenso y descenso')

@section('content')
{{-- HU-057: propuesta con la tabla final y las reglas vigentes; cada versión conserva las reglas con que se generó --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-4xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $grupo->categoria?->nombre }} · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">Temporada {{ $grupo->temporada?->nombre }}. Aplicar el cambio de categoría se hace después, al validar y cerrar la temporada.</p>
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
            <p class="font-semibold">No se guardó la propuesta:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section aria-labelledby="titulo-nueva" class="space-y-3">
        <h3 id="titulo-nueva" class="text-lg font-semibold text-white">Propuesta con las reglas vigentes</h3>

        @if ($revision['problemas'] !== [])
            <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
                <p class="font-semibold">Todavía no se puede generar:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($revision['problemas'] as $problema)
                        <li>{{ $problema }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <p class="text-sm text-gray-300">Vista previa con la versión {{ $revision['regla']?->version }} de las reglas de la temporada. Nada se guarda hasta confirmar.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach (['Ascienden' => $revision['asciende'], 'Descienden' => $revision['desciende']] as $titulo => $equipos)
                    <div class="rounded-lg border border-gray-700 p-4">
                        <p class="font-semibold text-white">{{ $titulo }} ({{ count($equipos) }})</p>
                        <ul class="mt-2 space-y-1 text-sm text-gray-300">
                            @forelse ($equipos as $equipo)
                                <li>{{ $equipo['posicion'] }}.° {{ $equipo['nombre'] }} · {{ $equipo['puntos'] }} pts</li>
                            @empty
                                <li class="text-gray-400">Ninguno</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>

            @if ($puedeGenerar)
                <form method="POST" action="{{ route('grupos.ascenso-descenso.store', $grupo) }}"
                    onsubmit="return confirm('¿Guardar esta propuesta? Quedará como una versión nueva con las reglas actuales.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400">
                        Guardar propuesta
                    </button>
                </form>
            @endif
        @endif
    </section>

    <section aria-labelledby="titulo-versiones" class="space-y-3">
        <h3 id="titulo-versiones" class="text-lg font-semibold text-white">Propuestas guardadas</h3>
        @forelse ($versiones as $version => $renglones)
            @php($primero = $renglones->first())
            <div class="rounded-lg border border-gray-700 p-4" data-version="{{ $version }}">
                <p class="font-semibold text-white">Versión {{ $version }}</p>
                <p class="text-xs text-gray-400">
                    {{ $primero->generadaPor?->name ?? 'Sin dato' }} · {{ $primero->created_at?->format('d/m/Y H:i') }} ·
                    reglas versión {{ $primero->regla_version }} (ascienden {{ $primero->reglas_ascenso_descenso['equipos_ascienden'] ?? 0 }}, descienden {{ $primero->reglas_ascenso_descenso['equipos_descienden'] ?? 0 }})
                </p>
                <ul class="mt-2 space-y-1 text-sm text-gray-300">
                    @foreach ($renglones as $renglon)
                        <li>
                            <x-badge :variant="$renglon->movimiento === 'asciende' ? 'success' : 'danger'">{{ $renglon->movimiento === 'asciende' ? 'Asciende' : 'Desciende' }}</x-badge>
                            {{ $renglon->posicion }}.° {{ $renglon->equipo?->nombre }} · {{ $renglon->puntos }} pts
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-gray-400">Todavía no hay propuestas guardadas para este grupo.</p>
        @endforelse
    </section>
</div>
@endsection
