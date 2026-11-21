@extends('layouts.admin')

@section('title', 'Expedientes Disciplinarios')

@section('content')
<main class="mx-auto max-w-7xl p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Régimen Disciplinario · Tribunal de Penas</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Expedientes Disciplinarios</h1>
            <p class="mt-1 text-sm text-gray-400">Expedientes generados a partir de incidencias, tarjetas y reportes arbitrales de las cédulas (HU-044 / RF-10.1).</p>
        </div>
    </div>

    @if (session('success'))
        <div role="status" class="mb-6 rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-emerald-200">{{ session('success') }}</div>
    @endif

    @if (session('aviso'))
        <div role="status" class="mb-6 rounded-lg border border-amber-700 bg-amber-950 p-4 text-amber-100">{{ session('aviso') }}</div>
    @endif

    <!-- Filtros y Búsqueda -->
    <section class="mb-6 rounded-xl border border-gray-700 bg-gray-800 p-4">
        <form method="GET" action="{{ route('disciplina.expedientes.index') }}" class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[240px]">
                <label for="buscar" class="sr-only">Buscar</label>
                <input type="text" name="buscar" id="buscar" value="{{ $busqueda }}" placeholder="Buscar por folio, equipo, título o palabras clave..." class="w-full rounded-lg border border-gray-600 bg-gray-900 px-4 py-2 text-sm text-white placeholder-gray-400 focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label for="estado" class="sr-only">Estado</label>
                <select name="estado" id="estado" class="rounded-lg border border-gray-600 bg-gray-900 px-4 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                    <option value="">Todos los estados</option>
                    <option value="Abierto" {{ $estadoFiltro === 'Abierto' ? 'selected' : '' }}>Abierto</option>
                    <option value="Resuelto" {{ $estadoFiltro === 'Resuelto' ? 'selected' : '' }}>Resuelto</option>
                </select>
            </div>

            <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Filtrar</button>
            @if ($busqueda || $estadoFiltro)
                <a href="{{ route('disciplina.expedientes.index') }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-300 hover:bg-gray-700">Limpiar</a>
            @endif
        </form>
    </section>

    <!-- Listado de Expedientes -->
    <section class="rounded-xl border border-gray-700 bg-gray-800 overflow-hidden">
        @if ($expedientes->isEmpty())
            <div class="p-10 text-center text-gray-400">
                <p class="text-base">No se encontraron expedientes disciplinarios con los criterios seleccionados.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-200">
                    <thead class="border-b border-gray-700 bg-gray-900/60 text-xs uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Folio</th>
                            <th class="px-6 py-3">Partido / Jornada</th>
                            <th class="px-6 py-3">Involucrados</th>
                            <th class="px-6 py-3">Presuntas Infracciones (REF-05)</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3">Fecha</th>
                            <th class="px-6 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach ($expedientes as $exp)
                            <tr class="hover:bg-gray-750/50">
                                <td class="px-6 py-4 font-mono font-bold text-emerald-400">
                                    {{ $exp->folio ?? ('EXP-#'.$exp->id) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-white">
                                        {{ $exp->partido?->local?->nombre ?? 'Local' }} vs {{ $exp->partido?->visitante?->nombre ?? 'Visitante' }}
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        Jornada {{ $exp->partido?->jornada?->numero_jornada ?? 'N/A' }} · {{ $exp->origen }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-gray-700 px-2.5 py-0.5 text-xs text-gray-200">
                                        {{ $exp->involucrados_count }} sujetos
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($exp->articulos as $art)
                                            <span class="inline-flex items-center rounded bg-red-950/80 border border-red-800 px-2 py-0.5 text-xs font-medium text-red-200" title="{{ $art->descripcion }}">
                                                {{ $art->codigo }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-400">{{ $exp->articulo?->codigo ?? 'Sin tipificar' }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($exp->estaAbierto())
                                        <span class="inline-flex rounded-full bg-amber-900/60 border border-amber-700 px-2.5 py-0.5 text-xs font-semibold text-amber-200">
                                            Abierto
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-900/60 border border-emerald-700 px-2.5 py-0.5 text-xs font-semibold text-emerald-200">
                                            Resuelto
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-400">
                                    {{ $exp->fecha_apertura ? $exp->fecha_apertura->format('d/m/Y H:i') : $exp->created_at->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('disciplina.expedientes.show', $exp) }}" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">
                                        Revisar
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($expedientes->hasPages())
                <div class="border-t border-gray-700 p-4">
                    {{ $expedientes->links() }}
                </div>
            @endif
        @endif
    </section>
</main>
@endsection
