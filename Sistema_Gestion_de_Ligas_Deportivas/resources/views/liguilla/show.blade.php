@extends('layouts.admin')

@php($nombreFase = \App\Models\FaseTorneo::NOMBRES[$fase])
@php($esCampeonDeCampeones = $fase === \App\Models\FaseTorneo::CAMPEON_DE_CAMPEONES)
@php($esConsolacion = $fase === \App\Models\FaseTorneo::CONSOLACION)

@section('title', $nombreFase.' de '.$grupo->nombre.' - SGLD')
@section('header', $nombreFase)

@section('content')
{{-- HU-055: cruces 1° vs N°, 2° vs (N-1)°... desde la tabla final; cada llave conserva las reglas usadas (RNF-36). HU-056: rondas, avances, campeón y subcampeón.
     HU-060: la misma pantalla para la liguilla de consolación (campeón alterno) y el Campeón de Campeones --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-4xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $grupo->categoria?->nombre }} · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">Temporada {{ $grupo->temporada?->nombre }}</p>
        </div>
        <nav aria-label="Fases finales" class="flex flex-wrap gap-x-4 gap-y-2 self-start text-sm">
            <a href="{{ route('grupos.tabla', $grupo) }}" class="text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">Ver tabla de posiciones</a>
            @foreach (['grupos.liguilla' => 'Liguilla', 'grupos.consolacion' => 'Consolación', 'grupos.campeon-de-campeones' => 'Campeón de Campeones'] as $destino => $etiqueta)
                @if ($destino === $ruta)
                    <span class="text-white font-semibold" aria-current="page">{{ $etiqueta }}</span>
                @else
                    <a href="{{ route($destino, $grupo) }}" class="text-green-400 hover:underline focus:outline-none focus:ring-2 focus:ring-green-400 rounded">{{ $etiqueta }}</a>
                @endif
            @endforeach
        </nav>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-semibold">{{ $llaves->isEmpty() ? 'No se generaron las llaves:' : 'No se creó la ronda:' }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($llaves->isNotEmpty())
        @php($primera = $llaves->first())
        <p class="text-sm text-gray-300">
            Generadas por {{ $primera->generadaPor?->name ?? 'Sin dato' }} el {{ $primera->created_at?->format('d/m/Y H:i') }}
            con la versión {{ $primera->regla_version ?? '—' }} de las reglas de la temporada{{ $fase === \App\Models\FaseTorneo::LIGUILLA ? ' y '.($primera->criterios_clasificacion['clasifican_liguilla'] ?? '—').' equipos clasificados' : '' }}.
            @if ($esCampeonDeCampeones)
                El campeón de liga (1.° de la tabla general) juega un partido contra el campeón de liguilla; si empatan, gana el mejor ubicado en la tabla general.
            @else
                Cada llave se juega a un partido; si empatan, avanza el mejor ubicado en la tabla general.
            @endif
        </p>

        {{-- HU-056 CA2 y HU-060: campeón y subcampeón al resolverse la final; el campeón alterno en la consolación --}}
        @if ($esCampeonDeCampeones && $primera->estaResuelta())
            <div class="rounded-lg border border-yellow-500/40 bg-yellow-500/10 p-4" data-campeones>
                <p class="text-lg font-bold text-yellow-200">Campeón de Campeones: {{ $primera->avanza?->nombre }}</p>
                <p class="text-sm text-yellow-100">
                    {{ $primera->esPaseDirecto() ? 'Ganó la liga y la liguilla, así que es Campeón de Campeones sin jugar.' : 'Ganó el partido de Campeón de Campeones.' }}
                </p>
            </div>
        @elseif ($campeones !== null)
            <div class="rounded-lg border border-yellow-500/40 bg-yellow-500/10 p-4" data-campeones>
                <p class="text-lg font-bold text-yellow-200">{{ $esConsolacion ? 'Campeón alterno' : 'Campeón' }}: {{ $campeones['campeon']->nombre }}</p>
                <p class="text-sm text-yellow-100">{{ $esConsolacion ? 'Subcampeón de consolación' : 'Subcampeón' }}: {{ $campeones['subcampeon']->nombre }}</p>
            </div>
        @endif

        @foreach ($rondas as $numero => $ronda)
            <x-table :caption="$ronda['nombre']">
                <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Llave</th>
                        <th scope="col" class="px-4 py-3">Equipo</th>
                        <th scope="col" class="px-4 py-3">Marcador</th>
                        <th scope="col" class="px-4 py-3">Equipo</th>
                        <th scope="col" class="px-4 py-3">Resultado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach ($ronda['llaves'] as $llave)
                        <tr data-llave="{{ $llave->orden }}">
                            <td class="px-4 py-3 text-white">{{ $llave->orden }}</td>
                            <td class="px-4 py-3"><span class="text-white">{{ $llave->local?->nombre }}</span> ({{ $llave->lugar_local }}°)</td>
                            @if ($llave->esPaseDirecto())
                                <td class="px-4 py-3 text-gray-400">—</td>
                                <td class="px-4 py-3 text-gray-400">Sin rival</td>
                                <td class="px-4 py-3 text-green-300">{{ $esCampeonDeCampeones ? 'Campeón de liga y de liguilla' : 'Pasa directo' }}</td>
                            @else
                                <td class="px-4 py-3 text-white">{{ $llave->estaResuelta() ? $llave->marcador_global_local.' - '.$llave->marcador_global_visitante : 'vs' }}</td>
                                <td class="px-4 py-3"><span class="text-white">{{ $llave->visitante?->nombre }}</span> ({{ $llave->lugar_visitante }}°)</td>
                                <td class="px-4 py-3">
                                    @if ($llave->estaResuelta())
                                        <span class="text-green-300">Avanza {{ $llave->avanza?->nombre }}</span>
                                        <span class="block text-red-300">Eliminado: {{ $llave->eliminadoId() === $llave->equipo_local_id ? $llave->local?->nombre : $llave->visitante?->nombre }}</span>
                                    @elseif ($llave->partido !== null)
                                        <span class="text-gray-300">Jornada {{ $llave->partido->jornada?->numero_jornada }} · partido {{ $llave->partido->estado }}</span>
                                    @else
                                        <span class="text-gray-400">Falta crear la ronda</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endforeach

        @if ($proxima['ronda'] !== null && $puedeCrearRonda)
            @php($inicio = old('fecha_inicio'))
            @php($fin = old('fecha_fin'))
            @php($accion = $esCampeonDeCampeones ? 'Crear el partido' : 'Crear la ronda '.$proxima['ronda'])
            <form method="POST" action="{{ route($ruta.'.rondas', $grupo) }}" class="space-y-4 border-t border-gray-700 pt-5">
                @csrf
                <h3 class="text-lg font-semibold text-white">{{ $accion }}</h3>
                <p class="text-sm text-gray-300">Se crea una jornada de {{ \App\Models\FaseTorneo::EN_TEXTO[$fase] }} con un partido en borrador por llave. Después el Programador de Partidos les asigna cancha, árbitro y horario.</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="fecha_inicio" class="mb-1 block text-sm font-medium text-gray-200">Fecha de inicio <span class="text-red-400">*</span></label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ is_string($inicio) ? $inicio : '' }}" required
                               @error('fecha_inicio') aria-invalid="true" aria-describedby="fecha-inicio-error" @enderror
                               class="w-full min-w-0 rounded-lg border border-gray-600 bg-gray-900 px-3 py-3 text-white focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/40">
                        @error('fecha_inicio')
                            <p id="fecha-inicio-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="fecha_fin" class="mb-1 block text-sm font-medium text-gray-200">Fecha de fin <span class="text-red-400">*</span></label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="{{ is_string($fin) ? $fin : '' }}" required
                               @error('fecha_fin') aria-invalid="true" aria-describedby="fecha-fin-error" @enderror
                               class="w-full min-w-0 rounded-lg border border-gray-600 bg-gray-900 px-3 py-3 text-white focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/40">
                        @error('fecha_fin')
                            <p id="fecha-fin-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400">
                    {{ $accion }}
                </button>
            </form>
        @elseif ($proxima['aviso'] !== null)
            <p class="text-sm text-gray-400">{{ $proxima['aviso'] }}</p>
        @endif
    @elseif ($revision['problemas'] !== [])
        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">
            <p class="font-semibold">{{ $esCampeonDeCampeones ? 'Todavía no se puede registrar el Campeón de Campeones:' : 'Todavía no se pueden generar las llaves:' }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($revision['problemas'] as $problema)
                    <li>{{ $problema }}</li>
                @endforeach
            </ul>
        </div>
    @else
        @if ($esCampeonDeCampeones)
            <p class="text-sm text-gray-300">Vista previa: el campeón de liga (1.° de la tabla general) contra el campeón de liguilla. Nada se guarda hasta confirmar.</p>
        @elseif ($esConsolacion)
            <p class="text-sm text-gray-300">Vista previa con los lugares {{ $revision['desde'] }}.° a {{ $revision['hasta'] }}.° de la tabla final ({{ $revision['clasifican'] }} equipos), calculada con la versión {{ $revision['regla']?->version }} de las reglas. Al confirmar se recalcula la tabla con las reglas vigentes; nada se guarda hasta entonces.</p>
        @else
            <p class="text-sm text-gray-300">Vista previa con los {{ $revision['clasifican'] }} primeros lugares de la tabla final, calculada con la versión {{ $revision['regla']?->version }} de las reglas. Al confirmar se recalcula la tabla con las reglas vigentes; nada se guarda hasta entonces.</p>
        @endif

        <x-table caption="Cruces propuestos">
            <thead class="bg-gray-900 text-xs uppercase text-gray-400">
                <tr>
                    <th scope="col" class="px-4 py-3">Llave</th>
                    <th scope="col" class="px-4 py-3">Equipo</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">contra</span></th>
                    <th scope="col" class="px-4 py-3">Equipo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-700">
                @foreach ($revision['cruces'] as $cruce)
                    <tr>
                        <td class="px-4 py-3 text-white">{{ $cruce['orden'] }}</td>
                        <td class="px-4 py-3"><span class="text-white">{{ $cruce['local']['nombre'] }}</span> ({{ $cruce['local']['posicion'] }}°)</td>
                        <td class="px-4 py-3 text-gray-400">vs</td>
                        @if ($cruce['visitante'] === null)
                            <td class="px-4 py-3 text-green-300">{{ $esCampeonDeCampeones ? 'Es el mismo equipo: Campeón de Campeones sin jugar' : 'Pasa directo' }}</td>
                        @else
                            <td class="px-4 py-3"><span class="text-white">{{ $cruce['visitante']['nombre'] }}</span> ({{ $cruce['visitante']['posicion'] }}°)</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        @if ($puedeGenerar)
            <form method="POST" action="{{ route($ruta.'.store', $grupo) }}"
                onsubmit="return confirm('¿Generar las llaves de {{ \App\Models\FaseTorneo::EN_TEXTO[$fase] }}? Quedarán registradas con las reglas actuales.');">
                @csrf
                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400">
                    {{ $esCampeonDeCampeones ? 'Registrar Campeón de Campeones' : 'Generar llaves' }}
                </button>
            </form>
        @endif
    @endif
</div>
@endsection
