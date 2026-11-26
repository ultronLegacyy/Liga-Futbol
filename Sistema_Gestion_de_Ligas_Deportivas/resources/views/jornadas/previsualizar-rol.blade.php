@extends('layouts.admin')

@section('title', 'Previsualización del Rol - '.$grupo->nombre.' - SGLD')
@section('header', 'Programación de Partidos')

@section('content')
<div class="space-y-6">
    {{-- Encabezado y resumen del cálculo --}}
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold text-white">Propuesta de Rol Regular</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-500/20 text-green-400 border border-green-500/30">
                        Algoritmo Berger (HU-032)
                    </span>
                </div>
                <p class="text-sm text-gray-400 mt-1">
                    {{ $grupo->nombre }} · {{ $grupo->categoria->nombre }} · {{ $grupo->temporada->nombre }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('grupos.generar-rol', $grupo) }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-400 transition">
                    Modificar Parámetros
                </a>
            </div>
        </div>

        {{-- Tarjetas de métricas del rol generado --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
            <div class="bg-gray-900/70 border border-gray-700 rounded-lg p-3 text-center">
                <span class="text-xs text-gray-400 block uppercase">Equipos</span>
                <span class="text-xl font-bold text-white">{{ $propuesta['total_equipos'] }}</span>
            </div>
            <div class="bg-gray-900/70 border border-gray-700 rounded-lg p-3 text-center">
                <span class="text-xs text-gray-400 block uppercase">Jornadas</span>
                <span class="text-xl font-bold text-green-400">{{ $propuesta['total_jornadas'] }}</span>
            </div>
            <div class="bg-gray-900/70 border border-gray-700 rounded-lg p-3 text-center">
                <span class="text-xs text-gray-400 block uppercase">Formato</span>
                <span class="text-xl font-bold text-blue-400">{{ $propuesta['vueltas'] == 2 ? '2 Vueltas' : '1 Vuelta' }}</span>
            </div>
            <div class="bg-gray-900/70 border border-gray-700 rounded-lg p-3 text-center">
                <span class="text-xs text-gray-400 block uppercase">Modalidad</span>
                @if ($grupo->tieneEquiposImpares())
                    <span class="text-sm font-bold text-yellow-400">Descanso rotativo</span>
                @else
                    <span class="text-sm font-bold text-green-300">Pares continuos</span>
                @endif
            </div>
        </div>

        @if (session('error'))
            <x-alert type="error" :message="session('error')" />
        @endif

        @if ($errors->has('fixture_payload'))
            <x-alert type="error" :message="$errors->first('fixture_payload')" />
        @elseif ($errors->has('sobrescribir'))
            <x-alert type="error" :message="$errors->first('sobrescribir')" />
        @elseif ($errors->has('grupo'))
            <x-alert type="error" :message="$errors->first('grupo')" />
        @elseif ($errors->any())
            <x-alert type="error" :message="$errors->first()" />
        @endif

        @if ($sobrescribir && isset($partidosPreviosCount) && $partidosPreviosCount > 0)
            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4 text-sm text-yellow-300">
                <p class="font-semibold">Aviso de reemplazo de programación:</p>
                <p class="text-xs text-yellow-300/90 mt-1">
                    Esta confirmación reemplazará {{ $grupo->jornadas()->count() }} jornada(s) y eliminará {{ $partidosPreviosCount }} partido(s) previamente registrados.
                    @if (isset($partidosConArbitroCount) && $partidosConArbitroCount > 0)
                        <span class="block text-yellow-400 font-semibold mt-1">Atención: {{ $partidosConArbitroCount }} de los partidos a eliminar ya cuentan con árbitro asignado.</span>
                    @endif
                </p>
            </div>
        @endif

        @if (! empty($asignarHorarios) && ! empty($propuesta['resumen_horarios']))
            @php($resumenHorarios = $propuesta['resumen_horarios'])
            <div class="bg-indigo-950/30 border border-indigo-500/40 rounded-lg p-4 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-indigo-200">Resultado preliminar de horarios automáticos (HU-074)</h3>
                    <span class="text-xs text-gray-300">Tiempo de cálculo: {{ $resumenHorarios['duracion_ms'] ?? 0 }} ms</span>
                </div>
                <p class="text-xs text-gray-300">
                    Asignados con sede y hora: <strong class="text-green-300">{{ $resumenHorarios['asignados'] }}</strong> de {{ $resumenHorarios['total_partidos'] }}
                    | Preferencias de equipo aplicadas: <strong class="text-indigo-300">{{ $resumenHorarios['preferencias_respetadas'] ?? 0 }}</strong>
                    @if (($resumenHorarios['sin_asignar'] ?? 0) > 0)
                        | Pendientes sin asignar: <strong class="text-amber-300">{{ $resumenHorarios['sin_asignar'] }}</strong>
                    @endif
                </p>
            </div>
        @endif

        @if (! empty($propuesta['conflictos']))
            <div class="bg-amber-950/40 border border-amber-500/50 rounded-lg p-4 space-y-2">
                <h3 class="text-sm font-bold text-amber-200">Conflictos o déficits detectados (HU-033 / RNF-19)</h3>
                <p class="text-xs text-amber-200/80">
                    La propuesta parcial no bloquea la confirmación del rol; los partidos listados permanecerán en borrador sin horario para su ajuste manual o ampliación de capacidad.
                </p>
                <div class="space-y-2 mt-2">
                    @foreach ($propuesta['conflictos'] as $conflicto)
                        <div class="rounded border border-amber-500/30 bg-gray-900/70 p-2.5 text-xs space-y-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-semibold text-white">Jornada {{ $conflicto['jornada_numero'] }} — {{ $conflicto['enfrentamiento'] }}</span>
                                <span class="text-amber-300 font-medium">{{ $conflicto['regla_origen'] }}</span>
                            </div>
                            <p class="text-gray-300"><strong class="text-gray-200">Causa:</strong> {{ $conflicto['causa'] }}</p>
                            <p class="text-emerald-300"><strong>Sugerencia:</strong> {{ $conflicto['sugerencia'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Desglose jornada por jornada --}}
    <div class="space-y-4">
        @foreach ($propuesta['jornadas'] as $itemJornada)
            <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-5 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-700/70 pb-3">
                    <div class="flex items-center gap-3">
                        <span class="px-3 py-1 bg-gray-700 rounded-lg font-bold text-white text-sm">
                            Jornada {{ $itemJornada['numero_jornada'] }}
                        </span>
                        <span class="text-xs text-gray-300">
                            {{ \Carbon\Carbon::parse($itemJornada['fecha_inicio'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($itemJornada['fecha_fin'])->format('d/m/Y') }}
                        </span>
                    </div>

                    @if ($itemJornada['equipo_descanso_nombre'])
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400">Equipo en descanso (RF-06.2):</span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-500/20 text-yellow-300 border border-yellow-500/40">
                                {{ $itemJornada['equipo_descanso_nombre'] }}
                            </span>
                        </div>
                    @endif
                </div>

                {{-- Partidos de la jornada --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                    @forelse ($itemJornada['partidos'] as $index => $partido)
                        <div class="bg-gray-900/60 border border-gray-700/60 rounded-lg p-3 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-green-400 flex-1 truncate text-left" title="{{ $partido['equipo_local_nombre'] }}">
                                    {{ $partido['equipo_local_nombre'] }}
                                    <span class="text-[10px] text-gray-400 block font-normal">Local</span>
                                </span>
                                <span class="px-2 py-0.5 bg-gray-800 text-gray-400 text-xs font-bold rounded uppercase">
                                    VS
                                </span>
                                <span class="font-medium text-blue-400 flex-1 truncate text-right" title="{{ $partido['equipo_visitante_nombre'] }}">
                                    {{ $partido['equipo_visitante_nombre'] }}
                                    <span class="text-[10px] text-gray-400 block font-normal">Visitante</span>
                                </span>
                            </div>
                            @if (! empty($asignarHorarios))
                                <div class="border-t border-gray-800 pt-1.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                                    @if (! empty($partido['cancha_nombre']) && ! empty($partido['hora_partido']))
                                        <span class="text-indigo-300">
                                            {{ $partido['cancha_nombre'] }} | {{ $partido['fecha_partido'] }} a las {{ $partido['hora_partido'] }} hrs
                                        </span>
                                        @if (! empty($partido['preferencia_aplicada']))
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300">Preferencia</span>
                                        @endif
                                    @else
                                        <span class="text-amber-300">
                                            Sin horario asignado: {{ $partido['conflicto']['codigo_regla'] ?? 'PENDIENTE' }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin partidos calculados para esta jornada.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    {{-- Formulario de confirmación --}}
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <h3 class="text-base font-semibold text-white">¿Desea confirmar y persistir este rol regular?</h3>
            <p class="text-xs text-gray-400 mt-1">
                Se registrarán {{ $propuesta['total_jornadas'] }} jornadas con sus respectivos enfrentamientos en la base de datos bajo transacción segura.
            </p>
        </div>

        <form method="POST" action="{{ route('grupos.confirmar-rol', $grupo) }}" class="flex items-center gap-3">
            @csrf
            <input type="hidden" name="fixture_payload" value="{{ json_encode($propuesta) }}">
            <input type="hidden" name="sobrescribir" value="{{ $sobrescribir ? '1' : '0' }}">
            <input type="hidden" name="asignar_horarios" value="{{ ! empty($asignarHorarios) ? '1' : '0' }}">
            <input type="hidden" name="respetar_capacidad_cancha" value="{{ ! empty($respetarCapacidadCancha) ? '1' : '0' }}">
            @if (! empty($horariosSeleccionados))
                <input type="hidden" name="horarios_texto" value="{{ implode(',', $horariosSeleccionados) }}">
            @endif
            @foreach (($canchasSeleccionadas ?? []) as $canchaIdSel)
                <input type="hidden" name="cancha_ids[]" value="{{ $canchaIdSel }}">
            @endforeach

            <a href="{{ route('grupos.generar-rol', $grupo) }}" class="px-4 py-2.5 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-400 transition">
                Cancelar
            </a>
            <button
                type="submit"
                class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium text-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition"
            >
                Confirmar y Programar Rol
            </button>
        </form>
    </div>
</div>
@endsection
