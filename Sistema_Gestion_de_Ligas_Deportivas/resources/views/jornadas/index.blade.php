@extends('layouts.admin')

@section('title', 'Jornadas - '.$grupo->nombre.' - SGLD')
@section('header', 'Programación de Partidos')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Jornadas · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">
                {{ $grupo->categoria->nombre }} · {{ $grupo->temporada->nombre }}
            </p>
            <div class="mt-2 flex items-center gap-2">
                <span class="text-xs text-gray-300">Equipos en el grupo: {{ $equipos->count() }}</span>
                @if ($grupo->tieneEquiposImpares())
                    <x-badge variant="warning">Número impar: requiere descanso</x-badge>
                @else
                    <x-badge variant="gray">Número par</x-badge>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('categorias.show', $grupo->categoria) }}" class="text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm px-3 py-2">
                Volver a la categoría
            </a>
            @can('create', [App\Models\Jornada::class, $grupo])
                <a href="{{ route('grupos.generar-rol', $grupo) }}" class="px-4 py-2 bg-indigo-600 text-white font-medium text-sm rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 transition">
                    Generar rol automático
                </a>
                @if ($jornadas->isNotEmpty())
                    <form action="{{ route('grupos.asignar-horarios', $grupo) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-cyan-600 text-white font-medium text-sm rounded-lg hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-400 transition">
                            Auto-asignar horarios
                        </button>
                    </form>
                @endif
                <a href="{{ route('grupos.jornadas.create', $grupo) }}" class="px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                    + Nueva jornada
                </a>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if (session('error'))
        <x-alert type="error" :message="session('error')" />
    @endif

    @if (session('programacion_conflictos'))
        <div class="bg-amber-950/40 border border-amber-500/50 rounded-xl p-4 space-y-2">
            <h3 class="text-sm font-bold text-amber-200">Partidos sin asignación completa de horario</h3>
            <p class="text-xs text-amber-200/80">
                Los encuentros válidos ya fueron asignados. Revise las siguientes causas antes de publicar:
            </p>
            <div class="space-y-2 mt-2">
                @foreach (session('programacion_conflictos', []) as $conflicto)
                    <div class="rounded border border-amber-500/30 bg-gray-900/70 p-2.5 text-xs space-y-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-white">Jornada {{ $conflicto['jornada_numero'] ?? '' }} — {{ $conflicto['enfrentamiento'] ?? '' }}</span>
                            <span class="text-amber-300 font-medium">{{ $conflicto['regla_origen'] ?? '' }}</span>
                        </div>
                        <p class="text-gray-300"><strong class="text-gray-200">Causa:</strong> {{ $conflicto['causa'] ?? '' }}</p>
                        <p class="text-emerald-300"><strong>Sugerencia:</strong> {{ $conflicto['sugerencia'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- HU-036: rechazo del borrado de una jornada (por ejemplo, con una cédula capturada) --}}
    @if ($errors->any())
        <x-alert type="error" :message="$errors->first()" />
    @endif

    <x-table caption="Listado de jornadas del grupo">
        <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
            <tr>
                <th scope="col" class="px-4 py-3 text-left">Jornada</th>
                <th scope="col" class="px-4 py-3 text-left">Fechas</th>
                <th scope="col" class="px-4 py-3 text-center">Partidos</th>
                <th scope="col" class="px-4 py-3 text-left">Equipo en Descanso (RF-06.2)</th>
                <th scope="col" class="px-4 py-3 text-right">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-700">
            @forelse ($jornadas as $jornada)
                <tr>
                    <td class="px-4 py-3 font-semibold text-white whitespace-nowrap">
                        Jornada {{ $jornada->numero_jornada }}
                    </td>
                    <td class="px-4 py-3 text-gray-300 text-sm whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($jornada->fecha_inicio)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($jornada->fecha_fin)->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-center text-sm">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-200">
                            {{ $jornada->partidos->count() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        @if ($jornada->equipoDescanso)
                            <x-badge variant="info">{{ $jornada->equipoDescanso->nombre }}</x-badge>
                        @elseif ($grupo->tieneEquiposImpares())
                            <x-badge variant="warning">Sin asignar</x-badge>
                        @else
                            <span class="text-gray-500 text-xs">No aplica</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap text-sm">
                        <a href="{{ route('jornadas.show', $jornada) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                            Ver / Partidos
                        </a>

                        @can('update', $jornada)
                            <a href="{{ route('jornadas.edit', $jornada) }}" class="text-yellow-400 hover:underline focus:outline-none focus:ring-2 focus:ring-yellow-400 rounded">
                                Editar
                            </a>
                        @endcan

                        @can('delete', $jornada)
                            <form action="{{ route('jornadas.destroy', $jornada) }}" method="POST" class="inline" onsubmit="return confirm('¿Deseas eliminar esta jornada y sus partidos asociados?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400 hover:underline focus:outline-none focus:ring-2 focus:ring-red-400 rounded">
                                    Eliminar
                                </button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-gray-400">
                        No hay jornadas registradas para este grupo.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    @if ($jornadas->hasPages())
        <div class="pt-4">
            {{ $jornadas->links() }}
        </div>
    @endif
</div>
@endsection
