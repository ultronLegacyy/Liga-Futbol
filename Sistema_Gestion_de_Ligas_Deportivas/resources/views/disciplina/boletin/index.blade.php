@extends('layouts.admin')

@section('title', 'Boletín Oficial de Sancionados y Castigados')

@section('content')
<main class="mx-auto max-w-7xl p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">
                Tribunal de Penas y Comisión Disciplinaria
            </p>
            <h1 class="mt-2 text-3xl font-bold text-white">
                Boletín Oficial de Sancionados y Castigados
            </h1>
            <p class="mt-1 text-sm text-gray-400">
                Publicación oficial de resoluciones firmes, cómputo de vigencia de castigos y registro de inhabilitaciones (HU-046 / RF-10.3, RNF-44).
            </p>
        </div>
        <div class="flex gap-3">
            @can('viewAny', App\Models\ExpedienteDisciplinario::class)
                <a href="{{ route('disciplina.expedientes.index') }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-300 hover:bg-gray-700">
                    Ver Expedientes Disciplinarios
                </a>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div role="status" class="mb-6 rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    @if (session('aviso'))
        <div role="status" class="mb-6 rounded-lg border border-amber-700 bg-amber-950 p-4 text-amber-100">
            {{ session('aviso') }}
        </div>
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

    <!-- Filtro de Temporada y Explicación Normativa -->
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-gray-700 bg-gray-800 p-5 md:col-span-1">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-300 mb-3">Filtrar por Temporada</h2>
            <form method="GET" action="{{ route('disciplina.boletin.index') }}" class="space-y-3">
                <select name="temporada_id" onchange="this.form.submit()" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                    <option value="">-- Todas las temporadas activas --</option>
                    @foreach ($temporadas as $temp)
                        <option value="{{ $temp->id }}" {{ (string)$temporadaSeleccionada === (string)$temp->id ? 'selected' : '' }}>
                            {{ $temp->nombre }}
                        </option>
                    @endforeach
                </select>
                @if ($temporadaSeleccionada)
                    <a href="{{ route('disciplina.boletin.index') }}" class="inline-block text-xs text-emerald-400 hover:underline">
                        Limpiar filtro de temporada
                    </a>
                @endif
            </form>
        </div>

        <div class="rounded-xl border border-emerald-800/60 bg-emerald-950/20 p-5 md:col-span-2">
            <h2 class="text-sm font-bold text-emerald-200 uppercase tracking-wider mb-2">Garantía de Vigencia y Cómputo Automático (RNF-44)</h2>
            <p class="text-xs text-gray-300 leading-relaxed">
                El boletín contiene únicamente sanciones validadas oficialmente por la Comisión Disciplinaria y autorizadas para publicación. Conforme los partidos sancionados se disputan y se cierran en sus respectivas cédulas arbitrales, el sistema descuenta los partidos restantes de forma inalterable. Al completar la sanción, el registro se retira automáticamente del boletín activo.
            </p>
        </div>
    </div>

    <!-- Sanciones Publicadas en el Boletín Oficial (HU-046 Criterio 2) -->
    <section aria-labelledby="boletin-titulo" class="rounded-xl border border-gray-700 bg-gray-800 p-6 mb-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4 border-b border-gray-700 pb-3">
            <div>
                <h2 id="boletin-titulo" class="text-xl font-bold text-white">
                    Sanciones Activas en el Boletín Oficial
                </h2>
                <p class="text-xs text-gray-400">
                    Castigos en vigor que inhabilitan la participación de jugadores en encuentros oficiales (IU-05).
                </p>
            </div>
            <span class="rounded-full bg-red-900/80 border border-red-700 px-3 py-1 text-xs font-bold text-red-200">
                {{ $boletin->count() }} {{ $boletin->count() === 1 ? 'sanción vigente' : 'sanciones vigentes' }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-200">
                <thead class="border-b border-gray-700 bg-gray-900/60 text-xs uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Expediente</th>
                        <th class="px-4 py-3">Sancionado</th>
                        <th class="px-4 py-3">Infracción / Artículo</th>
                        <th class="px-4 py-3">Tipo Sanción</th>
                        <th class="px-4 py-3">Cómputo Partidos</th>
                        <th class="px-4 py-3">Vigencia / Término</th>
                        <th class="px-4 py-3">Autorización (RNF-44)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @forelse ($boletin as $sancion)
                        <tr class="hover:bg-gray-750/50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($sancion->expediente)
                                    <a href="{{ route('disciplina.expedientes.show', $sancion->expediente) }}" class="font-mono text-emerald-400 hover:underline font-bold">
                                        {{ $sancion->expediente->folio ?? ('#' . $sancion->expediente_id) }}
                                    </a>
                                @else
                                    <span class="font-mono text-gray-400">#{{ $sancion->id }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($sancion->persona)
                                    <div class="font-bold text-white">
                                        {{ $sancion->persona->nombre }} {{ $sancion->persona->apellidos }}
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ $sancion->equipo?->nombre ?? 'Sin equipo vinculado' }}
                                    </div>
                                @elseif ($sancion->equipo)
                                    <div class="font-bold text-emerald-300">
                                        {{ $sancion->equipo->nombre }}
                                    </div>
                                    <div class="text-xs text-gray-400">Sanción a Club</div>
                                @else
                                    <span class="text-gray-400 italic">No especificado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded bg-red-950 border border-red-800 px-2 py-0.5 text-xs font-mono font-bold text-red-300">
                                    {{ $sancion->articulo?->codigo ?? 'Reglamento' }}
                                </span>
                                <p class="mt-1 text-xs text-gray-300 line-clamp-2">
                                    {{ $sancion->articulo?->descripcion ?? $sancion->descripcion_sancion }}
                                </p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex rounded-full bg-gray-700 px-2.5 py-0.5 text-xs font-semibold text-gray-200">
                                    {{ str_replace('_', ' ', $sancion->tipo_sancion) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($sancion->partidos_suspendidos > 0)
                                    <div class="font-bold text-white">
                                        Faltan {{ $sancion->partidosRestantes() }} {{ $sancion->partidosRestantes() === 1 ? 'partido' : 'partidos' }} ({{ $sancion->partidosRestantes() }} restantes)
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        ({{ $sancion->partidos_cumplidos }} de {{ $sancion->partidos_suspendidos }} cumplidos)
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Por temporalidad</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if ($sancion->fecha_fin)
                                    <div class="text-gray-200">Hasta: {{ $sancion->fecha_fin->format('d/m/Y') }}</div>
                                @elseif ($sancion->partidoHasta)
                                    <div class="text-gray-200">
                                        Hasta Jornada {{ $sancion->partidoHasta->jornada?->numero_jornada ?? 'N/A' }}
                                    </div>
                                @else
                                    <div class="text-amber-300 font-medium">Hasta cumplir partidos</div>
                                @endif
                                @if ($sancion->monto_multa)
                                    <div class="text-amber-400 font-semibold mt-0.5">
                                        Multa: ${{ number_format((float)$sancion->monto_multa, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-300">
                                <div><strong class="text-emerald-400">{{ $sancion->autorizado_por_cargo ?? $sancion->autorizadoPor?->name ?? 'Comisión Disciplinaria' }}</strong></div>
                                <div class="text-gray-400">
                                    {{ $sancion->autorizadoPor?->name ? $sancion->autorizadoPor->name . ' · ' : '' }}{{ $sancion->fecha_publicacion ? $sancion->fecha_publicacion->timezone(\App\Models\Sancion::ZONA_HORARIA)->format('d/m/Y H:i') : 'Publicación Oficial' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                No existen sanciones vigentes publicadas en el boletín para el criterio seleccionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- Panel de Administración: Sanciones Pendientes de Publicación (RNF-44) -->
    @if ($puedeAdministrar && $sancionesPendientes->count() > 0)
        <section aria-labelledby="pendientes-titulo" class="rounded-xl border border-amber-800/60 bg-amber-950/20 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4 border-b border-amber-800/40 pb-3">
                <div>
                    <h2 id="pendientes-titulo" class="text-xl font-bold text-amber-200">
                        Sanciones Vigentes Pendientes de Publicación Oficial (RNF-44)
                    </h2>
                    <p class="text-xs text-amber-300/80">
                        Sanciones dictaminadas que requieren autorización formal para figurar en el boletín oficial de castigados.
                    </p>
                </div>
                <span class="rounded-full bg-amber-900/80 border border-amber-600 px-3 py-1 text-xs font-bold text-amber-200">
                    {{ $sancionesPendientes->count() }} pendientes
                </span>
            </div>

            <form method="POST" action="{{ route('disciplina.boletin.publicar') }}" class="space-y-4">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-200">
                        <thead class="border-b border-gray-700 bg-gray-900/60 text-xs uppercase tracking-wider text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Seleccionar</th>
                                <th class="px-4 py-3">Expediente</th>
                                <th class="px-4 py-3">Sancionado</th>
                                <th class="px-4 py-3">Artículo / Sanción</th>
                                <th class="px-4 py-3">Validación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @foreach ($sancionesPendientes as $pend)
                                <tr class="hover:bg-gray-750/50">
                                    <td class="px-4 py-3">
                                        @if ($pend->estaValidada())
                                            <input type="checkbox" name="sanciones_ids[]" value="{{ $pend->id }}" class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-emerald-600 focus:ring-emerald-500">
                                        @else
                                            <input type="checkbox" disabled title="Requiere validación previa de la Comisión (RNF-44)" class="h-4 w-4 rounded border-gray-700 bg-gray-800 opacity-50 cursor-not-allowed">
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono text-emerald-400 text-xs font-bold">
                                        @if ($pend->expediente)
                                            <a href="{{ route('disciplina.expedientes.show', $pend->expediente) }}" class="hover:underline">
                                                {{ $pend->expediente->folio ?? ('#' . $pend->expediente_id) }}
                                            </a>
                                        @else
                                            #{{ $pend->expediente_id }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-white text-xs">
                                            {{ $pend->persona ? $pend->persona->nombre . ' ' . $pend->persona->apellidos : ($pend->equipo?->nombre ?? 'Club') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <span class="font-bold text-red-300">{{ $pend->articulo?->codigo ?? 'N/A' }}</span>
                                        · {{ $pend->partidos_suspendidos }} partidos / {{ str_replace('_', ' ', $pend->tipo_sancion) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        @if ($pend->estaValidada())
                                            <span class="text-emerald-400 font-semibold">Validada</span>
                                        @else
                                            <span class="text-amber-400 font-semibold">Pendiente de validación (RNF-44)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pt-4 border-t border-gray-700">
                    <div>
                        <label for="motivo_publicacion" class="block text-xs font-semibold text-gray-300 mb-1">
                            Motivo o Justificación de la Publicación Oficial * (RNF-30; el cargo autorizador se registra automáticamente según el rol de tu cuenta)
                        </label>
                        <input type="text" id="motivo_publicacion" name="motivo" required value="{{ old('motivo') }}" placeholder="Ej. Publicación del boletín semanal de sanciones de la jornada" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-amber-500 focus:outline-none">
                    </div>
                </div>

                @php($hayValidadas = $sancionesPendientes->contains(fn ($s) => $s->estaValidada()))
                <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                    @if (! $hayValidadas)
                        <span class="text-xs text-amber-300">
                            Valide primero al menos una sanción desde su expediente correspondiente (RNF-44) para habilitar la publicación oficial.
                        </span>
                    @else
                        <span></span>
                    @endif
                    <button type="submit" {{ $hayValidadas ? '' : 'disabled' }} class="rounded-lg px-5 py-2 text-sm font-bold text-white {{ $hayValidadas ? 'bg-amber-600 hover:bg-amber-500' : 'bg-gray-700 opacity-50 cursor-not-allowed' }}">
                        Autorizar y Publicar Sanciones Seleccionadas
                    </button>
                </div>
            </form>
        </section>
    @endif
</main>
@endsection
