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
                        <div class="bg-gray-900/60 border border-gray-700/60 rounded-lg p-3 flex items-center justify-between text-sm">
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
