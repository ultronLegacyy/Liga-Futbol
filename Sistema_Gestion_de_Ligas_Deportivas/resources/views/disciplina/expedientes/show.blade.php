@extends('layouts.admin')

@section('title', 'Expediente Disciplinario ' . ($expediente->folio ?? '#' . $expediente->id))

@section('content')
<main class="mx-auto max-w-6xl p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">
                Tribunal de Penas · Expediente {{ $expediente->folio ?? ('#' . $expediente->id) }}
            </p>
            <h1 class="mt-2 text-3xl font-bold text-white">
                {{ $expediente->titulo ?? 'Expediente Disciplinario' }}
            </h1>
            <p class="mt-1 text-sm text-gray-400">
                Apertura: {{ $expediente->fecha_apertura ? $expediente->fecha_apertura->format('d/m/Y H:i:s') : $expediente->created_at->format('d/m/Y H:i:s') }}
                · Origen: <span class="capitalize text-gray-300">{{ str_replace('_', ' ', $expediente->origen) }}</span>
                @if ($expediente->generado_automaticamente)
                    · <span class="rounded bg-gray-700 px-2 py-0.5 text-xs text-emerald-300">Generación Automática</span>
                @endif
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('disciplina.expedientes.index') }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-300 hover:bg-gray-700">
                Volver al listado
            </a>
            @if ($expediente->cedula)
                <a href="{{ route('cedulas.show', $expediente->partido_id) }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-300 hover:bg-gray-700">
                    Ver Cédula Arbitral
                </a>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div role="status" class="mb-6 rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-emerald-200">{{ session('success') }}</div>
    @endif

    @if (session('aviso'))
        <div role="status" class="mb-6 rounded-lg border border-amber-700 bg-amber-950 p-4 text-amber-100">{{ session('aviso') }}</div>
    @endif

    <!-- 1. Artículos Presuntamente Infringidos (HU-044 Criterio 2) -->
    <section aria-labelledby="articulos-titulo" class="mb-6 rounded-xl border border-red-800/60 bg-red-950/20 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4 border-b border-red-800/40 pb-3">
            <div>
                <h2 id="articulos-titulo" class="text-xl font-bold text-red-200">
                    Artículos Reglamentarios Presuntamente Infringidos (REF-05)
                </h2>
                <p class="text-xs text-red-300/80">
                    Catálogo de infracciones del Reglamento de Sanciones del Sector Amateur de la FMF imputadas preliminarmente.
                </p>
            </div>
            @if ($expediente->estaAbierto())
                <span class="inline-flex rounded-full bg-amber-900/80 border border-amber-600 px-3 py-1 text-xs font-semibold text-amber-200">
                    Estado: Abierto en Instrucción
                </span>
            @else
                <span class="inline-flex rounded-full bg-emerald-900/80 border border-emerald-600 px-3 py-1 text-xs font-semibold text-emerald-200">
                    Estado: Resuelto / Dictaminado
                </span>
            @endif
        </div>

        <div class="space-y-3">
            @forelse ($expediente->articulos as $art)
                <div class="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-red-900/50 bg-gray-900/70 p-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <span class="rounded bg-red-900 px-2.5 py-1 font-mono text-sm font-bold text-white">
                                {{ $art->codigo }}
                            </span>
                            <span class="text-sm font-semibold text-white">
                                Castigo base de referencia: {{ $art->castigo_base_partidos }} {{ $art->castigo_base_partidos === 1 ? 'partido' : 'partidos' }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-gray-200">
                            {{ $art->descripcion }}
                        </p>
                        @if ($art->pivot->observaciones)
                            <p class="mt-1 text-xs text-gray-400">
                                <em>Detección: {{ $art->pivot->observaciones }}</em>
                            </p>
                        @endif
                    </div>

                    @can('gestionar', $expediente)
                        @if ($expediente->articulos->count() > 1 && $expediente->estaAbierto())
                            <form method="POST" action="{{ route('disciplina.expedientes.articulos.destroy', [$expediente, $art]) }}" onsubmit="return confirm('¿Desestimar la imputación presunta de este artículo?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded border border-red-800 px-2.5 py-1 text-xs font-medium text-red-300 hover:bg-red-900/50">
                                    Desestimar
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            @empty
                <div class="rounded-lg border border-gray-700 bg-gray-900/40 p-4 text-center text-sm text-gray-400">
                    No se han registrado artículos en el catálogo para este expediente.
                </div>
            @endforelse
        </div>

        @can('gestionar', $expediente)
            @if ($expediente->estaAbierto())
                <div class="mt-6 border-t border-red-900/40 pt-4">
                    <h3 class="text-sm font-semibold text-gray-200 mb-2">Imputar artículo reglamentario adicional</h3>
                    <form method="POST" action="{{ route('disciplina.expedientes.articulos.store', $expediente) }}" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <div class="flex-1 min-w-[260px]">
                            <select name="articulo_id" required class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                <option value="">Seleccione un artículo del catálogo...</option>
                                @foreach ($catalogoArticulos as $catArt)
                                    @if (! $expediente->articulos->contains('id', $catArt->id))
                                        <option value="{{ $catArt->id }}">
                                            {{ $catArt->codigo }} — {{ Str::limit($catArt->descripcion, 80) }} (Base: {{ $catArt->castigo_base_partidos }} part.)
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <input type="text" name="observaciones" placeholder="Motivo o hecho que origina la imputación..." class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white placeholder-gray-400 focus:border-emerald-500 focus:outline-none">
                        </div>
                        <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600">
                            Agregar Imputación
                        </button>
                    </form>
                </div>
            @endif
        @endcan
    </section>

    <!-- 2. Personas y Equipos Involucrados (HU-044 Criterio 1) -->
    <section aria-labelledby="involucrados-titulo" class="mb-6 rounded-xl border border-gray-700 bg-gray-800 p-6">
        <h2 id="involucrados-titulo" class="mb-4 text-xl font-semibold text-white">
            Sujetos y Equipos Involucrados
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-200">
                <thead class="border-b border-gray-700 bg-gray-900/60 text-xs uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Involucrado</th>
                        <th class="px-4 py-3">Club / Equipo</th>
                        <th class="px-4 py-3">Calidad / Rol en Incidencia</th>
                        <th class="px-4 py-3">Participación Registrada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @forelse ($expediente->involucrados as $inv)
                        <tr class="hover:bg-gray-750/50">
                            <td class="px-4 py-3">
                                @if ($inv->persona)
                                    <div class="font-bold text-white">
                                        {{ $inv->persona->nombre }} {{ $inv->persona->apellidos }}
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        CURP: {{ $inv->persona->curp ?? 'No registrada' }}
                                    </div>
                                @else
                                    <span class="text-gray-400 italic">Sujeto colectivo / No individualizado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-emerald-300">
                                    {{ $inv->equipo?->nombre ?? 'Sin equipo específico' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if (str_contains($inv->tipo_involucrado, 'expulsado'))
                                    <span class="inline-flex rounded-full bg-red-900/70 border border-red-700 px-2.5 py-0.5 text-xs font-semibold text-red-200">
                                        Jugador Expulsado
                                    </span>
                                @elseif (str_contains($inv->tipo_involucrado, 'amonestado'))
                                    <span class="inline-flex rounded-full bg-amber-900/70 border border-amber-700 px-2.5 py-0.5 text-xs font-semibold text-amber-200">
                                        Jugador Amonestado
                                    </span>
                                @elseif (str_contains($inv->tipo_involucrado, 'implicado'))
                                    <span class="inline-flex rounded-full bg-purple-900/70 border border-purple-700 px-2.5 py-0.5 text-xs font-semibold text-purple-200">
                                        Equipo Implicado
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-700 px-2.5 py-0.5 text-xs text-gray-300 capitalize">
                                        {{ str_replace('_', ' ', $inv->tipo_involucrado) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-300">
                                {{ $inv->descripcion_participacion ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-400">
                                No se registraron personas ni equipos asociados en el expediente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- 3. Evidencias de la Cédula Arbitral (HU-044 Criterio 1) -->
    <section aria-labelledby="evidencias-titulo" class="mb-6 rounded-xl border border-gray-700 bg-gray-800 p-6">
        <h2 id="evidencias-titulo" class="mb-4 text-xl font-semibold text-white">
            Evidencias Registradas de la Cédula Arbitral
        </h2>
        <div class="space-y-3">
            @forelse ($expediente->evidencias as $evi)
                <div class="flex items-start gap-4 rounded-lg border border-gray-700 bg-gray-900/60 p-4">
                    <div class="flex-shrink-0">
                        @if ($evi->tipo_evidencia === 'evento_tarjeta')
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-900/60 border border-amber-700 text-xs font-bold text-amber-200">
                                {{ $evi->minuto ? $evi->minuto . "'" : 'TAR' }}
                            </span>
                        @elseif ($evi->tipo_evidencia === 'informe_arbitral')
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-900/60 border border-blue-700 text-xs font-bold text-blue-200">
                                INF
                            </span>
                        @else
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-700 text-xs font-bold text-gray-200">
                                INC
                            </span>
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs uppercase font-semibold text-gray-400">
                                {{ str_replace('_', ' ', $evi->tipo_evidencia) }}
                            </span>
                            @if ($evi->minuto)
                                <span class="text-xs text-gray-500">· Minuto {{ $evi->minuto }}</span>
                            @endif
                        </div>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-100">
                            {{ $evi->descripcion }}
                        </p>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-gray-700 bg-gray-900/40 p-4 text-center text-sm text-gray-400">
                    No se adjuntaron evidencias para este expediente.
                </div>
            @endforelse
        </div>
    </section>

    <!-- 4. Datos del Encuentro y Contexto Deportivo -->
    <section aria-labelledby="partido-titulo" class="rounded-xl border border-gray-700 bg-gray-800 p-6">
        <h2 id="partido-titulo" class="mb-4 text-xl font-semibold text-white">Datos del Encuentro</h2>
        <dl class="grid gap-4 text-sm md:grid-cols-3">
            <div>
                <dt class="text-gray-400">Temporada / Torneo</dt>
                <dd class="text-white font-medium">{{ $expediente->partido?->jornada?->grupo?->categoria?->temporada?->nombre ?? 'Temporada no vinculada' }}</dd>
            </div>
            <div>
                <dt class="text-gray-400">Jornada</dt>
                <dd class="text-white font-medium">Jornada {{ $expediente->partido?->jornada?->numero_jornada ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-gray-400">Cancha / Sede</dt>
                <dd class="text-white font-medium">{{ $expediente->partido?->cancha?->nombre ?? 'Cancha no asignada' }}</dd>
            </div>
            <div>
                <dt class="text-gray-400">Equipo Local</dt>
                <dd class="text-emerald-400 font-bold">{{ $expediente->partido?->local?->nombre ?? 'Local' }}</dd>
            </div>
            <div>
                <dt class="text-gray-400">Equipo Visitante</dt>
                <dd class="text-emerald-400 font-bold">{{ $expediente->partido?->visitante?->nombre ?? 'Visitante' }}</dd>
            </div>
            <div>
                <dt class="text-gray-400">Marcador en Cédula</dt>
                <dd class="text-white font-mono font-bold">
                    {{ $expediente->cedula?->goles_local ?? 0 }} - {{ $expediente->cedula?->goles_visitante ?? 0 }}
                </dd>
            </div>
        </dl>
    </section>
</main>
@endsection
