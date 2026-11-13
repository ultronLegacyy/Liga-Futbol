@extends('layouts.admin')

@section('title', 'Jornada '.$jornada->numero_jornada.' - '.$grupo->nombre.' - SGLD')
@section('header', 'Programación de Partidos')

@section('content')
<div class="space-y-6">
    {{-- Encabezado principal --}}
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-white">Jornada {{ $jornada->numero_jornada }}</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                        {{ $grupo->nombre }}
                    </span>
                    @php
                        $estadoJornadaConfig = match($jornada->estado) {
                            'borrador' => ['variant' => 'default', 'label' => 'Borrador'],
                            'en_revision' => ['variant' => 'warning', 'label' => 'En Revisión'],
                            'aprobada' => ['variant' => 'info', 'label' => 'Aprobada'],
                            'publicada' => ['variant' => 'success', 'label' => 'Publicada Oficial'],
                            default => ['variant' => 'default', 'label' => ucfirst((string) $jornada->estado)],
                        };
                    @endphp
                    <x-badge :variant="$estadoJornadaConfig['variant']">{{ $estadoJornadaConfig['label'] }}</x-badge>
                </div>
                <p class="text-sm text-gray-400 mt-1">
                    {{ $grupo->categoria->nombre }} · {{ $grupo->temporada->nombre }} · Rama {{ $grupo->categoria->rama ?? 'N/A' }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Periodo: {{ \Carbon\Carbon::parse($jornada->fecha_inicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($jornada->fecha_fin)->format('d/m/Y') }}
                </p>
                @if ($jornada->aprobado_por_id)
                    <p class="text-xs text-indigo-300 mt-1">
                        Aprobada por: <span class="font-semibold">{{ $jornada->aprobadoPor->name ?? 'Comité' }}</span>
                        @if ($jornada->fecha_aprobacion)
                            el {{ \Carbon\Carbon::parse($jornada->fecha_aprobacion)->format('d/m/Y H:i') }} hrs
                        @endif
                    </p>
                @endif
                @if ($jornada->fecha_publicacion)
                    <p class="text-xs text-green-400 mt-0.5">
                        Publicada oficialmente el {{ \Carbon\Carbon::parse($jornada->fecha_publicacion)->format('d/m/Y H:i') }} hrs
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('grupos.jornadas.index', $grupo) }}" class="text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded text-sm px-3 py-2">
                    Volver a jornadas
                </a>

                {{-- Flujo de publicación semanal (HU-030 / SCRUM-199 / SCRUM-201) --}}
                @if ($jornada->esBorrador())
                    @can('enviarRevision', $jornada)
                        <form action="{{ route('jornadas.enviar-revision', $jornada) }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="px-3 py-2 bg-amber-600/90 hover:bg-amber-600 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 transition"
                                @disabled($jornada->partidos->isEmpty())
                                title="{{ $jornada->partidos->isEmpty() ? 'Agrega partidos antes de enviar a revisión' : 'Enviar programación a revisión' }}"
                            >
                                Enviar a Revisión
                            </button>
                        </form>
                    @endcan
                @elseif ($jornada->esEnRevision())
                    @can('aprobar', $jornada)
                        <form action="{{ route('jornadas.aprobar', $jornada) }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 transition"
                            >
                                Aprobar Programación
                            </button>
                        </form>
                    @endcan
                @elseif ($jornada->esAprobada())
                    @can('publicar', $jornada)
                        <form action="{{ route('jornadas.publicar', $jornada) }}" method="POST" class="inline" onsubmit="return confirm('¿Confirmas la publicación oficial de la jornada? Todos los partidos pasarán a estado programado.');">
                            @csrf
                            <button
                                type="submit"
                                class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition"
                                @disabled(! empty($conflictos))
                                title="{{ ! empty($conflictos) ? 'No se puede publicar debido a conflictos existentes' : 'Publicar programación oficial' }}"
                            >
                                Publicar Programación
                            </button>
                        </form>
                    @endcan
                @endif

                @can('update', $jornada)
                    <a href="{{ route('jornadas.edit', $jornada) }}" class="px-3 py-2 bg-yellow-600/80 hover:bg-yellow-600 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 transition">
                        Editar Jornada
                    </a>
                @endcan
            </div>
        </div>

        @if (session('success'))
            <div class="mt-4">
                <x-alert type="success" :message="session('success')" />
            </div>
        @endif

        @if (session('error'))
            <div class="mt-4">
                <x-alert type="error" :message="session('error')" />
            </div>
        @endif

        {{-- Alerta de errores de validacion o rechazo (HU-030, HU-036) --}}
        @if ($errors->any())
            <div class="mt-4">
                <x-alert type="error" :message="$errors->first()" />
            </div>
        @endif

        {{-- Alerta de conflictos de programación (HU-030 / SCRUM-200) --}}
        @if (! empty($conflictos))
            <div class="mt-4 bg-red-950/40 border border-red-600/50 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 text-red-400 font-bold text-base mt-0.5">!</div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-bold text-red-200">Conflictos de programación detectados (HU-030):</h4>
                        <p class="text-xs text-red-300">La programación contiene solapamientos o colisiones que deben corregirse antes de su publicación:</p>
                        <ul class="list-disc list-inside text-xs text-red-300 space-y-0.5 mt-2">
                            @foreach ($conflictos as $conflicto)
                                <li>{{ $conflicto }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Tarjeta de descanso de la jornada (HU-028 / RF-06.2 / SCRUM-159) --}}
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>Equipo en descanso</span>
                    @if ($grupo->tieneEquiposImpares())
                        <x-badge variant="warning">Grupo impar ({{ $equipos->count() }} equipos)</x-badge>
                    @endif
                </h3>
                <p class="text-xs text-gray-400 mt-1">
                    En grupos impares, cada jornada un equipo descansa y no debe tener partidos programados (RF-06.2).
                </p>
                <div class="mt-2">
                    @if ($jornada->equipoDescanso)
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-300">Descansa esta jornada:</span>
                            <span class="px-3 py-1 rounded-md text-sm font-bold bg-green-500/20 text-green-300 border border-green-500/40">
                                {{ $jornada->equipoDescanso->nombre }}
                            </span>
                        </div>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm bg-gray-700/60 text-gray-300 border border-gray-600">
                            Ningún equipo descansa en esta jornada
                        </span>
                    @endif
                </div>
            </div>

            @can('update', $jornada)
                <form action="{{ route('jornadas.descanso.update', $jornada) }}" method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <label for="descanso_equipo_id" class="sr-only">Seleccionar equipo en descanso</label>
                    <select
                        name="equipo_descanso_id"
                        id="descanso_equipo_id"
                        class="rounded-lg bg-gray-900 border border-gray-600 text-white text-sm p-2.5 focus:outline-none focus:ring-2 focus:ring-green-400"
                    >
                        <option value="">-- Sin descanso --</option>
                        @foreach ($equipos as $equipo)
                            <option value="{{ $equipo->id }}" @selected($jornada->equipo_descanso_id == $equipo->id)>
                                {{ $equipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition whitespace-nowrap">
                        Actualizar descanso
                    </button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Listado de partidos (IU-16 / SCRUM-156) --}}
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
                <h3 class="text-lg font-bold text-white">Partidos programados (IU-16)</h3>
                <p class="text-xs text-gray-400">Asignación manual de local, visitante, cancha, fecha, hora y árbitro.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($jornada->partidos->isEmpty())
                    @can('update', $jornada)
                        <form action="{{ route('jornadas.generar-partidos', $jornada) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-400 transition">
                                Generar partidos automáticamente (HU-032)
                            </button>
                        </form>
                    @endcan
                @endif

                @can('create', [App\Models\Partido::class, $jornada])
                    <a href="{{ route('jornadas.partidos.create', $jornada) }}" class="px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                        + Programar partido
                    </a>
                @endcan
            </div>
        </div>

        <x-table caption="Partidos programados de la jornada">
            <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Partido</th>
                    <th scope="col" class="px-4 py-3 text-left">Fecha y Hora</th>
                    <th scope="col" class="px-4 py-3 text-left">Cancha</th>
                    <th scope="col" class="px-4 py-3 text-left">Árbitro</th>
                    <th scope="col" class="px-4 py-3 text-center">Estado</th>
                    <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @forelse ($jornada->partidos as $partido)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-white">
                            <div class="flex items-center gap-2">
                                <span class="text-green-400">{{ $partido->local->nombre ?? 'Local' }}</span>
                                <span class="text-xs text-gray-400">vs</span>
                                <span class="text-blue-400">{{ $partido->visitante->nombre ?? 'Visitante' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-300 whitespace-nowrap">
                            <div>{{ $partido->fecha_partido ? \Carbon\Carbon::parse($partido->fecha_partido)->format('d/m/Y') : 'Sin fecha' }}</div>
                            <div class="text-xs text-gray-400">
                                @if ($partido->hora_partido)
                                    {{ \Carbon\Carbon::parse($partido->hora_partido)->format('H:i') }} hrs
                                @else
                                    Sin hora
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-300">
                            <div>{{ $partido->cancha?->nombre ?? 'Sin cancha asignada' }}</div>
                            <div class="text-xs text-gray-500">{{ $partido->cancha?->direccion_ubicacion ?? 'Sin dirección' }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-300">
                            {{ $partido->arbitro->persona->nombre_completo ?? 'Árbitro #'.$partido->arbitro_id }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $estadoPartidoVariant = match($partido->estado) {
                                    'confirmado' => 'success',
                                    'cancelado' => 'danger',
                                    'suspendido', 'en juego' => 'warning',
                                    'programado', 'reprogramado' => 'info',
                                    default => 'default',
                                };
                            @endphp
                            <x-badge :variant="$estadoPartidoVariant">{{ $partido->estado }}</x-badge>
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap text-sm">
                            <a href="{{ route('partidos.show', $partido) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                                Ver
                            </a>

                            <a href="{{ route('partidos.historial', $partido) }}" class="text-indigo-400 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-400 rounded" title="Bitácora de cambios y reprogramaciones">
                                Historial
                            </a>

                            @can('reprogramar', $partido)
                                <a href="{{ route('partidos.reprogramar', $partido) }}" class="text-purple-400 hover:underline focus:outline-none focus:ring-2 focus:ring-purple-400 rounded">
                                    Reprogramar
                                </a>
                            @endcan

                            @can('update', $partido)
                                <a href="{{ route('partidos.edit', $partido) }}" class="text-yellow-400 hover:underline focus:outline-none focus:ring-2 focus:ring-yellow-400 rounded">
                                    Editar
                                </a>
                            @endcan

                            @can('delete', $partido)
                                <form action="{{ route('partidos.destroy', $partido) }}" method="POST" class="inline" onsubmit="return confirm('¿Deseas eliminar este partido en borrador?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:underline focus:outline-none focus:ring-2 focus:ring-red-400 rounded">
                                        Eliminar
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-gray-500 italic" title="Historial oficial protegido contra eliminación (HU-031)">
                                    Historial protegido
                                </span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                            No hay partidos programados en esta jornada todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </div>
</div>
@endsection
