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
                @if ($expediente->estaResuelto())
                    · <span class="rounded bg-emerald-900/80 border border-emerald-600 px-2 py-0.5 text-xs font-semibold text-emerald-200">Resuelto / Dictaminado</span>
                @else
                    · <span class="rounded bg-amber-900/80 border border-amber-600 px-2 py-0.5 text-xs font-semibold text-amber-200">Abierto en Instrucción</span>
                @endif
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('disciplina.expedientes.index') }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-300 hover:bg-gray-700">
                Volver al listado
            </a>
            <a href="{{ route('disciplina.boletin.index') }}" class="rounded-lg border border-emerald-600 bg-emerald-950/40 px-4 py-2 text-sm font-semibold text-emerald-300 hover:bg-emerald-900/60">
                Boletín de Castigados
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

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-lg border border-red-700 bg-red-950/80 p-4 text-red-200">
            <p class="font-bold">Se encontraron los siguientes errores:</p>
            <ul class="mt-2 list-disc pl-5 text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
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
                        @if ($expediente->estaAbierto())
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

    <!-- 4. Audiencias y Comparecencias Disciplinarias (SCRUM-57 / HU-045 / RF-10.2) -->
    <section aria-labelledby="audiencias-titulo" class="mb-6 rounded-xl border border-indigo-800/60 bg-indigo-950/20 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4 border-b border-indigo-800/40 pb-3">
            <div>
                <h2 id="audiencias-titulo" class="text-xl font-bold text-indigo-200">
                    Audiencias Disciplinarias y Alegatos de Defensa (RF-10.2)
                </h2>
                <p class="text-xs text-indigo-300/80">
                    Registro de comparecencias, recepción de alegatos y garantías de audiencia previa a resolución.
                </p>
            </div>
            <span class="rounded-full bg-indigo-900/70 border border-indigo-700 px-3 py-1 text-xs text-indigo-300">
                Total audiencias: {{ $expediente->audiencias->count() }}
            </span>
        </div>

        <div class="space-y-4">
            @forelse ($expediente->audiencias as $aud)
                <div class="rounded-lg border border-indigo-900/60 bg-gray-900/70 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-700 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-indigo-900 px-2 py-0.5 text-xs font-bold text-white">
                                Sesión #{{ $aud->id }}
                            </span>
                            <span class="text-sm font-semibold text-white">
                                {{ $aud->fecha_audiencia->format('d/m/Y H:i') }}
                            </span>
                            <span class="text-xs text-gray-400">· Lugar / Medio: <strong class="text-gray-300">{{ $aud->lugar_o_medio }}</strong></span>
                        </div>
                        <div class="text-xs text-gray-400">
                            Registrado por: <span class="text-indigo-300 font-medium">{{ $aud->registradoPor?->nombre_completo ?? $aud->registradoPor?->name ?? 'Usuario ID ' . $aud->registrado_por_usuario_id }}</span>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-2 text-sm">
                        <div>
                            <span class="font-semibold text-gray-300 text-xs uppercase tracking-wide">Comparecientes:</span>
                            <p class="mt-1 text-gray-200 whitespace-pre-line bg-gray-800/50 p-2.5 rounded border border-gray-700/60">
                                {{ $aud->comparecientes }}
                            </p>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-300 text-xs uppercase tracking-wide">Alegatos y Argumentos de Defensa:</span>
                            <p class="mt-1 text-gray-200 whitespace-pre-line bg-gray-800/50 p-2.5 rounded border border-gray-700/60">
                                {{ $aud->alegatos_defensa }}
                            </p>
                        </div>
                    </div>

                    @if ($aud->resolucion)
                        <div class="mt-3 pt-2 border-t border-gray-800 text-xs text-indigo-200">
                            <strong class="text-indigo-300 uppercase">Acuerdo previo de la comparecencia:</strong> {{ $aud->resolucion }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-gray-700 bg-gray-900/40 p-4 text-center text-sm text-gray-400">
                    No se han celebrado audiencias formales registradas para este expediente.
                </div>
            @endforelse
        </div>

        @can('create', App\Models\AudienciaDisciplinaria::class)
            @if ($expediente->estaAbierto())
                <div class="mt-6 border-t border-indigo-900/50 pt-5">
                    <h3 class="text-base font-bold text-indigo-200 mb-3">Registrar Nueva Audiencia de Comparecencia</h3>
                    <form method="POST" action="{{ route('disciplina.expedientes.audiencias.store', $expediente) }}" class="space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="fecha_audiencia" class="block text-xs font-semibold text-gray-300 mb-1">Fecha y Hora de Comparecencia *</label>
                                <input type="datetime-local" id="fecha_audiencia" name="fecha_audiencia" required value="{{ old('fecha_audiencia', now(\App\Models\Sancion::ZONA_HORARIA)->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                            </div>
                            <div>
                                <label for="lugar_o_medio" class="block text-xs font-semibold text-gray-300 mb-1">Lugar o Medio de Celebración *</label>
                                <input type="text" id="lugar_o_medio" name="lugar_o_medio" placeholder="Ej. Sala de Sesiones de la Liga / Videoconferencia Teams" required value="{{ old('lugar_o_medio') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="comparecientes" class="block text-xs font-semibold text-gray-300 mb-1">Comparecientes (Personas presentes, cargos y acreditaciones) *</label>
                            <textarea id="comparecientes" name="comparecientes" rows="2" required placeholder="Nombres de árbitros, directivos, jugador imputado, representante legal..." class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:outline-none">{{ old('comparecientes') }}</textarea>
                        </div>

                        <div>
                            <label for="alegatos_defensa" class="block text-xs font-semibold text-gray-300 mb-1">Alegatos y Declaraciones de Descargo *</label>
                            <textarea id="alegatos_defensa" name="alegatos_defensa" rows="3" required placeholder="Argumentación presentada por la parte inculpada, pruebas orales o documentales ofrecidas..." class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:outline-none">{{ old('alegatos_defensa') }}</textarea>
                        </div>

                        <div>
                            <label for="resolucion_audiencia" class="block text-xs font-semibold text-gray-300 mb-1">Acuerdos Previos de la Audiencia (Opcional)</label>
                            <input type="text" id="resolucion_audiencia" name="resolucion" placeholder="Acuerdos o conclusiones preliminares alcanzadas durante la diligencia..." value="{{ old('resolucion') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:outline-none">
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-lg bg-indigo-700 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-600">
                                Registrar Audiencia Formal
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan
    </section>

    <!-- 5. Dictamen de Sanciones y Resolución Inmutable (SCRUM-57 / RF-10.2, SCRUM-58 / RF-10.3, RNF-44) -->
    <section aria-labelledby="sanciones-titulo" class="mb-6 rounded-xl border border-emerald-800/60 bg-emerald-950/20 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4 border-b border-emerald-800/40 pb-3">
            <div>
                <h2 id="sanciones-titulo" class="text-xl font-bold text-emerald-200">
                    Resolución y Sanciones Dictaminadas (RF-10.2 / RF-10.3)
                </h2>
                <p class="text-xs text-emerald-300/80">
                    Historial de sanciones dictaminadas, cálculo de vigencia deportiva y publicación autorizada en boletín.
                </p>
            </div>
            @if ($expediente->estaResuelto())
                <span class="rounded-full bg-emerald-900/80 border border-emerald-500 px-3 py-1 text-xs font-semibold text-emerald-100">
                    Expediente Concluido y Resuelto
                </span>
            @endif
        </div>

        <!-- Historial inmutable de sanciones aplicadas en este expediente -->
        <div class="space-y-4 mb-6">
            @forelse ($expediente->sanciones as $sancion)
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-800 pb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-gray-800 border border-gray-600 px-2.5 py-0.5 text-xs font-mono font-bold text-white">
                                    Sanción #{{ $sancion->id }}
                                </span>
                                @if ($sancion->persona)
                                    <span class="font-bold text-white text-base">
                                        {{ $sancion->persona->nombre }} {{ $sancion->persona->apellidos }}
                                    </span>
                                    <span class="text-xs text-gray-400">({{ $sancion->equipo?->nombre ?? 'Sin equipo' }})</span>
                                @elseif ($sancion->equipo)
                                    <span class="font-bold text-emerald-300 text-base">
                                        {{ $sancion->equipo->nombre }}
                                    </span>
                                    <span class="text-xs text-gray-400">(Sanción a Club)</span>
                                @endif
                                <span class="rounded bg-red-900/70 border border-red-700 px-2 py-0.5 text-xs font-semibold text-red-200">
                                    {{ str_replace('_', ' ', $sancion->tipo_sancion) }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">
                                Artículo aplicado: <strong class="text-gray-200">{{ $sancion->articulo?->codigo ?? 'N/A' }}</strong> — {{ $sancion->articulo?->descripcion ?? 'Sin descripción' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($sancion->estaVigente())
                                <span class="rounded-full bg-red-900/80 border border-red-600 px-3 py-1 text-xs font-bold text-red-200">
                                    Vigente
                                </span>
                            @elseif ($sancion->estaCumplida())
                                <span class="rounded-full bg-blue-900/80 border border-blue-600 px-3 py-1 text-xs font-bold text-blue-200">
                                    Cumplida
                                </span>
                            @else
                                <span class="rounded-full bg-gray-800 border border-gray-600 px-3 py-1 text-xs text-gray-300 capitalize">
                                    {{ $sancion->estado }}
                                </span>
                            @endif

                            @if ($sancion->publicada_en_boletin)
                                <span class="rounded-full bg-emerald-900/80 border border-emerald-600 px-3 py-1 text-xs font-bold text-emerald-200">
                                    En Boletín Oficial
                                </span>
                            @else
                                <span class="rounded-full bg-gray-800 border border-gray-700 px-3 py-1 text-xs text-gray-400">
                                    No Publicada
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-3 text-xs">
                        <div class="bg-gray-800/40 p-3 rounded border border-gray-800">
                            <span class="text-gray-400 uppercase font-semibold">Cómputo de Partidos:</span>
                            <div class="mt-1 text-sm font-bold text-white">
                                {{ $sancion->partidos_suspendidos }} programados · <span class="text-emerald-400">{{ $sancion->partidos_cumplidos }} cumplidos</span>
                            </div>
                            <div class="text-gray-400 mt-0.5">
                                Restantes: <strong class="text-amber-300">{{ $sancion->partidosRestantes() }}</strong>
                            </div>
                        </div>

                        <div class="bg-gray-800/40 p-3 rounded border border-gray-800">
                            <span class="text-gray-400 uppercase font-semibold">Vigencia Temporal:</span>
                            <div class="mt-1 text-gray-200">
                                Desde: {{ $sancion->fecha_inicio ? $sancion->fecha_inicio->format('d/m/Y') : 'Inmediata' }}
                            </div>
                            <div class="text-gray-200">
                                Hasta: {{ $sancion->fecha_fin ? $sancion->fecha_fin->format('d/m/Y') : ($sancion->partidoHasta ? 'Jornada ' . $sancion->partidoHasta->jornada?->numero_jornada : 'Indefinida') }}
                            </div>
                            @if ($sancion->monto_multa)
                                <div class="text-amber-400 font-semibold mt-1">
                                    Multa: ${{ number_format((float)$sancion->monto_multa, 2) }}
                                </div>
                            @endif
                        </div>

                        <div class="bg-gray-800/40 p-3 rounded border border-gray-800">
                            <span class="text-gray-400 uppercase font-semibold">Validación y Publicación (RNF-44):</span>
                            <div class="mt-1 text-gray-200">
                                Validación:
                                @if ($sancion->estaValidada())
                                    <span class="text-emerald-300 font-medium">Sí ({{ $sancion->validadoPor?->name ?? 'Usuario' }} el {{ $sancion->fecha_validacion?->timezone(\App\Models\Sancion::ZONA_HORARIA)->format('d/m/Y') }})</span>
                                @else
                                    <span class="text-amber-400">Pendiente de validación</span>
                                @endif
                            </div>
                            <div class="text-gray-200">
                                Autorización Boletín:
                                @if ($sancion->publicada_en_boletin)
                                    <span class="text-emerald-300 font-medium">{{ $sancion->autorizado_por_cargo ?? $sancion->autorizadoPor?->name ?? 'Comisión' }} ({{ $sancion->fecha_publicacion?->timezone(\App\Models\Sancion::ZONA_HORARIA)->format('d/m/Y H:i') }})</span>
                                @else
                                    <span class="text-gray-400">Sin publicar</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 bg-gray-800/70 p-3 rounded border border-gray-700/60 text-xs">
                        <strong class="text-gray-300 uppercase">Motivo y Fundamentación de la Resolución:</strong>
                        <p class="mt-1 text-gray-200 whitespace-pre-line">{{ $sancion->motivo_resolucion ?? $sancion->descripcion_sancion }}</p>
                    </div>

                    <!-- Botones de Gestión de Sanción (Validar, Publicar en Boletín) -->
                    <div class="mt-4 pt-3 border-t border-gray-800 flex flex-wrap items-center justify-end gap-3 text-xs">
                        @if (! $sancion->estaValidada() && $sancion->estaVigente())
                            @can('validar', $sancion)
                                <form method="POST" action="{{ route('disciplina.sanciones.validar', $sancion) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="text" name="motivo" required minlength="5" value="Validación oficial de la sanción por la Comisión Disciplinaria" placeholder="Motivo de validación oficial..." class="rounded border border-gray-600 bg-gray-900 px-2 py-1 text-xs text-white placeholder-gray-500 focus:border-indigo-500 focus:outline-none min-w-[240px]">
                                    <button type="submit" class="rounded bg-indigo-700 px-3 py-1.5 font-semibold text-white hover:bg-indigo-600">
                                        Validar Sanción Oficialmente (RNF-44)
                                    </button>
                                </form>
                            @endcan
                        @elseif (! $sancion->publicada_en_boletin && $sancion->estaVigente())
                            @can('publicar', $sancion)
                                <form method="POST" action="{{ route('disciplina.sanciones.publicar-boletin', $sancion) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="motivo" value="Publicación individual autorizada en boletín oficial de castigados">
                                    <button type="submit" class="rounded bg-emerald-700 px-3 py-1.5 font-semibold text-white hover:bg-emerald-600">
                                        Publicar en Boletín Oficial
                                    </button>
                                </form>
                            @endcan
                        @elseif ($sancion->publicada_en_boletin)
                            @can('publicar', $sancion)
                                <form method="POST" action="{{ route('disciplina.sanciones.despublicar', $sancion) }}" onsubmit="return confirm('¿Retirar esta sanción del boletín de castigados?');">
                                    @csrf
                                    <input type="hidden" name="motivo" value="Retiro oficial de la sanción del boletín de castigados">
                                    <button type="submit" class="rounded border border-red-800 px-3 py-1.5 font-semibold text-red-300 hover:bg-red-900/40">
                                        Retirar del Boletín
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-gray-700 bg-gray-900/40 p-4 text-center text-sm text-gray-400">
                    No se han dictaminado sanciones formales para este expediente.
                </div>
            @endforelse
        </div>

        <!-- Formulario de Dictamen y Resolución Definitiva (RF-10.2 / RF-10.3) -->
        @can('create', App\Models\Sancion::class)
            @if ($expediente->estaAbierto())
                <div class="mt-6 border-t border-emerald-900/50 pt-5">
                    <h3 class="text-base font-bold text-emerald-200 mb-2">Dictaminar Resolución y Sanción Disciplinaria (RF-10.2)</h3>
                    <p class="text-xs text-gray-300 mb-4">
                        Al dictaminar la sanción, el sistema calculará automáticamente la vigencia de partidos (RF-10.3), generará los bloqueos de alineación (RF-10.4) y transicionará el expediente a estado <strong>Resuelto</strong>.
                    </p>

                    <form method="POST" action="{{ route('disciplina.expedientes.sanciones.store', $expediente) }}" class="space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="persona_id" class="block text-xs font-semibold text-gray-300 mb-1">Persona Sancionada (Jugador / Cuerpo Técnico)</label>
                                <select id="persona_id" name="persona_id" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                    <option value="">-- No individualizada / Sanción a Club --</option>
                                    @foreach ($expediente->involucrados as $inv)
                                        @if ($inv->persona)
                                            <option value="{{ $inv->persona->id }}" {{ old('persona_id') == $inv->persona->id ? 'selected' : '' }}>
                                                {{ $inv->persona->nombre }} {{ $inv->persona->apellidos }} ({{ $inv->equipo?->nombre ?? 'Equipo' }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="equipo_id" class="block text-xs font-semibold text-gray-300 mb-1">Equipo / Club Sancionado</label>
                                <select id="equipo_id" name="equipo_id" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                    <option value="">-- Sanción exclusivamente individual --</option>
                                    @if ($expediente->partido?->local)
                                        <option value="{{ $expediente->partido->local->id }}" {{ old('equipo_id') == $expediente->partido->local->id ? 'selected' : '' }}>
                                            Local: {{ $expediente->partido->local->nombre }}
                                        </option>
                                    @endif
                                    @if ($expediente->partido?->visitante)
                                        <option value="{{ $expediente->partido->visitante->id }}" {{ old('equipo_id') == $expediente->partido->visitante->id ? 'selected' : '' }}>
                                            Visitante: {{ $expediente->partido->visitante->nombre }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label for="articulo_id" class="block text-xs font-semibold text-gray-300 mb-1">Artículo del Catálogo Aplicado *</label>
                                <select id="articulo_id" name="articulo_id" required class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                    <option value="">Seleccione el artículo definitivo...</option>
                                    @foreach ($catalogoArticulos as $art)
                                        <option value="{{ $art->id }}" {{ old('articulo_id') == $art->id ? 'selected' : '' }}>
                                            {{ $art->codigo }} — {{ Str::limit($art->descripcion, 60) }} (Base: {{ $art->castigo_base_partidos }} p.)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="tipo_sancion" class="block text-xs font-semibold text-gray-300 mb-1">Tipo de Sanción *</label>
                                <select id="tipo_sancion" name="tipo_sancion" required class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                    <option value="partidos_suspension" {{ old('tipo_sancion', 'partidos_suspension') === 'partidos_suspension' ? 'selected' : '' }}>Suspensión por Partidos (Jugador)</option>
                                    <option value="suspension_temporal" {{ old('tipo_sancion') === 'suspension_temporal' ? 'selected' : '' }}>Suspensión Temporal (Fechas)</option>
                                    <option value="multa_economica" {{ old('tipo_sancion') === 'multa_economica' ? 'selected' : '' }}>Multa Económica</option>
                                    <option value="amonestacion" {{ old('tipo_sancion') === 'amonestacion' ? 'selected' : '' }}>Amonestación Escrita</option>
                                </select>
                            </div>

                            <div>
                                <label for="partidos_suspendidos" class="block text-xs font-semibold text-gray-300 mb-1">Partidos de Suspensión</label>
                                <input type="number" id="partidos_suspendidos" name="partidos_suspendidos" min="0" value="{{ old('partidos_suspendidos', 1) }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label for="fecha_inicio" class="block text-xs font-semibold text-gray-300 mb-1">Fecha de Inicio de Sanción</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio', now(\App\Models\Sancion::ZONA_HORARIA)->format('Y-m-d')) }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                            </div>
                            <div>
                                <label for="fecha_fin" class="block text-xs font-semibold text-gray-300 mb-1">Fecha de Término (Obligatoria si es Temporal)</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                            </div>
                            <div>
                                <label for="monto_multa" class="block text-xs font-semibold text-gray-300 mb-1">Monto de Multa en MXN (Opcional)</label>
                                <input type="number" id="monto_multa" name="monto_multa" min="0" step="0.01" placeholder="0.00" value="{{ old('monto_multa') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="motivo_resolucion" class="block text-xs font-semibold text-gray-300 mb-1">Fundamentación Jurídico-Deportiva de la Resolución *</label>
                            <textarea id="motivo_resolucion" name="motivo_resolucion" rows="3" required placeholder="Exposición motivada del fallo, valoración de pruebas de la cédula y considerandos del Tribunal..." class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-emerald-500 focus:outline-none">{{ old('motivo_resolucion') }}</textarea>
                        </div>

                        <div class="flex flex-wrap items-center gap-6 rounded-lg border border-gray-700 bg-gray-900/60 p-3 text-xs text-gray-200">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="validar_inmediatamente" value="1" {{ old('validar_inmediatamente') ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-emerald-600 focus:ring-emerald-500">
                                <span>Validar oficialmente en este acto por la Comisión Disciplinaria (RNF-44)</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between border-t border-gray-700 pt-4">
                            <span class="text-xs text-amber-300">
                                Aviso: El dictamen resolverá definitivamente la instrucción del expediente.
                            </span>
                            <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-500">
                                Dictaminar Sanción y Resolver Expediente
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan
    </section>

    <!-- 6. Datos del Encuentro y Contexto Deportivo -->
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect = document.getElementById('tipo_sancion');
    const partidosInput = document.getElementById('partidos_suspendidos');
    if (!tipoSelect || !partidosInput) return;

    const sincronizarCamposSancion = () => {
        const requierePartidos = tipoSelect.value === 'partidos_suspension';
        if (!requierePartidos) {
            partidosInput.value = '0';
        } else if (parseInt(partidosInput.value || '0', 10) <= 0) {
            partidosInput.value = '1';
        }

        const fechaFinInput = document.getElementById('fecha_fin');
        if (fechaFinInput) {
            fechaFinInput.required = tipoSelect.value === 'suspension_temporal';
        }
    };

    tipoSelect.addEventListener('change', sincronizarCamposSancion);
});
</script>
@endsection