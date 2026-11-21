@extends('layouts.admin')

@section('title', 'Capturar cédula en papel - SGLD')
@section('header', 'Capturar cédula en papel')

@section('content')
@php
    // Tras un error, el formulario vuelve con lo capturado; old() puede traer un arreglo y solo se pinta texto
    $enviado = old('clave_envio') !== null;
    $anterior = static fn (string $campo, mixed $omision = ''): string => is_scalar($valor = old($campo, $omision)) ? (string) $valor : '';
    $marcado = static fn (string $campo, bool $omision): bool => $enviado ? filter_var(old($campo, false), FILTER_VALIDATE_BOOLEAN) : $omision;
    $entrada = 'w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-400';
    $todos = $filas->flatten(1);
@endphp

<div class="max-w-5xl">
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 mb-6">
        <h2 class="text-xl font-bold text-white mb-1">{{ $partido->local?->nombre }} vs. {{ $partido->visitante?->nombre }}</h2>
        <p class="text-sm text-gray-400">
            Partido {{ $partido->id }} · {{ $partido->fecha_partido?->format('d/m/Y') ?? 'Sin fecha' }}
            {{ $partido->hora_partido ? '· '.substr((string) $partido->hora_partido, 0, 5) : '' }}
            · {{ $partido->cancha?->nombre ?? 'Sin cancha' }}
            @if ($partido->jornada?->grupo?->categoria) · {{ $partido->jornada->grupo->categoria->nombre }} @endif
        </p>
        <p class="text-sm text-gray-300 mt-3">Transcribe la cédula tal como viene en papel. Se aplican las mismas validaciones que en la captura del árbitro desde el celular; si un dato no es válido no se guarda nada y se indica qué corregir.</p>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @include('cedulas._hallazgos')

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-lg border border-red-700 bg-red-950 p-4 text-red-100">
            <p class="font-semibold">No se guardó la cédula. Corrige lo siguiente:</p>
            <ul class="mt-2 list-disc pl-5 text-sm">
                @foreach ($errors->all() as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($cedula && ($cedula->eventos->isNotEmpty() || $cedula->incidencias->isNotEmpty()))
        <div class="mb-6 rounded-lg border border-amber-700 bg-amber-950 p-4 text-amber-100 text-sm">
            <p class="font-semibold">Esta cédula ya tiene {{ $cedula->eventos->count() }} gol(es) o tarjeta(s) y {{ $cedula->incidencias->count() }} incidencia(s) capturados.</p>
            <p>Lo que escribas aquí se agrega; para quitar un registro equivocado, anúlalo desde la cédula.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('cedulas.fisica.store', $partido) }}" class="space-y-6" novalidate>
        @csrf
        <input type="hidden" name="clave_envio" value="{{ $anterior('clave_envio', $claveEnvio) }}">

        <fieldset class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
            <legend class="px-2 text-lg font-semibold text-white">Marcador</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['goles_local' => $partido->local?->nombre, 'goles_visitante' => $partido->visitante?->nombre] as $campo => $equipo)
                    <div>
                        <label for="{{ $campo }}" class="mb-1 block text-sm font-medium text-gray-200">Goles de {{ $equipo }}</label>
                        <input id="{{ $campo }}" name="{{ $campo }}" type="number" inputmode="numeric" min="0" max="99" required value="{{ $anterior($campo, $cedula?->{$campo} ?? 0) }}" class="{{ $entrada }}" @error($campo) aria-invalid="true" @enderror>
                        @error($campo) <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
            @error('marcador') <p class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror
        </fieldset>

        <fieldset class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
            <legend class="px-2 text-lg font-semibold text-white">Alineación</legend>
            <p class="text-sm text-gray-400 mb-4">Marca a quienes jugaron, su número de camiseta y al capitán de cada equipo.</p>
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ($equipos as $equipoId => $equipoNombre)
                    @php($jugadores = $filas->get($equipoId, collect()))
                    <section aria-labelledby="equipo-{{ $equipoId }}">
                        <h3 id="equipo-{{ $equipoId }}" class="font-semibold text-gray-100 mb-2">{{ $equipoNombre }}</h3>
                        @error('capitanes.'.$equipoId) <p class="mb-2 text-sm text-red-300">{{ $message }}</p> @enderror
                        @if ($jugadores->isEmpty())
                            <p class="text-sm text-gray-400">El equipo no tiene jugadores en su plantilla.</p>
                        @else
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-400">
                                        <th scope="col" class="py-1 pr-2">Jugó</th>
                                        <th scope="col" class="py-1 pr-2">Núm.</th>
                                        <th scope="col" class="py-1 pr-2">Jugador</th>
                                        <th scope="col" class="py-1">Capitán</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    @foreach ($jugadores as $jugador)
                                        @php($id = $jugador['jugador_id'])
                                        <tr>
                                            <td class="py-2 pr-2">
                                                <input type="hidden" name="alineacion[{{ $id }}][asistio]" value="0">
                                                <input id="asistio-{{ $id }}" type="checkbox" name="alineacion[{{ $id }}][asistio]" value="1" @checked($marcado("alineacion.$id.asistio", $jugador['asistio'])) class="h-5 w-5 rounded border-gray-500 bg-gray-900 text-green-600" aria-label="{{ $jugador['nombre'] }} jugó">
                                            </td>
                                            <td class="py-2 pr-2 w-20">
                                                <input name="alineacion[{{ $id }}][numero_camiseta]" type="number" inputmode="numeric" min="0" max="999" value="{{ $anterior("alineacion.$id.numero_camiseta", $jugador['numero_camiseta']) }}" class="{{ $entrada }} px-2 py-1" aria-label="Número de camiseta de {{ $jugador['nombre'] }}">
                                            </td>
                                            <td class="py-2 pr-2 text-white">
                                                <label for="asistio-{{ $id }}">{{ $jugador['nombre'] }}</label>
                                                @if ($jugador['estado'] && $jugador['estado'] !== 'Activo')
                                                    <span class="ml-1 text-xs text-amber-400">({{ $jugador['estado'] }})</span>
                                                @endif
                                                @error("alineacion.$id") <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                                                @error("alineacion.$id.asistio") <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                                                @error("alineacion.$id.numero_camiseta") <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="py-2 text-center">
                                                <input type="radio" name="capitanes[{{ $equipoId }}]" value="{{ $id }}" @checked($enviado ? $anterior("capitanes.$equipoId") === (string) $id : $jugador['es_capitan']) class="h-5 w-5 border-gray-500 bg-gray-900 text-green-600" aria-label="{{ $jugador['nombre'] }} es el capitán de {{ $equipoNombre }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
            <legend class="px-2 text-lg font-semibold text-white">Goles, amonestaciones y expulsiones</legend>
            <p class="text-sm text-gray-400 mb-4">Una fila por gol o tarjeta: la amarilla es la amonestación y la roja, la expulsión. Las filas vacías no se guardan.</p>
            <div class="space-y-3">
                @for ($fila = 0; $fila < $filasEventos; $fila++)
                    <div class="grid gap-2 sm:grid-cols-12 items-start">
                        <div class="sm:col-span-5">
                            <label for="evento-{{ $fila }}-jugador" class="sr-only">Jugador del evento {{ $fila + 1 }}</label>
                            <select id="evento-{{ $fila }}-jugador" name="eventos[{{ $fila }}][jugador_id]" class="{{ $entrada }}">
                                <option value="">Evento {{ $fila + 1 }}: jugador</option>
                                @foreach ($equipos as $equipoId => $equipoNombre)
                                    <optgroup label="{{ $equipoNombre }}">
                                        @foreach ($filas->get($equipoId, collect()) as $jugador)
                                            <option value="{{ $jugador['jugador_id'] }}" @selected($anterior("eventos.$fila.jugador_id") === (string) $jugador['jugador_id'])>{{ $jugador['numero_camiseta'] !== null ? $jugador['numero_camiseta'].' · ' : '' }}{{ $jugador['nombre'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="evento-{{ $fila }}-tipo" class="sr-only">Tipo del evento {{ $fila + 1 }}</label>
                            <select id="evento-{{ $fila }}-tipo" name="eventos[{{ $fila }}][tipo]" class="{{ $entrada }}">
                                <option value="">Tipo</option>
                                @foreach (['Gol' => 'Gol', 'Amarilla' => 'Amarilla (amonestación)', 'Roja' => 'Roja (expulsión)'] as $valor => $texto)
                                    <option value="{{ $valor }}" @selected($anterior("eventos.$fila.tipo") === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="evento-{{ $fila }}-minuto" class="sr-only">Minuto del evento {{ $fila + 1 }}</label>
                            <input id="evento-{{ $fila }}-minuto" name="eventos[{{ $fila }}][minuto]" type="number" inputmode="numeric" min="0" max="150" placeholder="Minuto" value="{{ $anterior("eventos.$fila.minuto") }}" class="{{ $entrada }}">
                        </div>
                        <label class="sm:col-span-2 flex items-center gap-2 text-sm text-gray-200 min-h-10">
                            <input type="checkbox" name="eventos[{{ $fila }}][autogol]" value="1" @checked($marcado("eventos.$fila.autogol", false)) class="h-5 w-5 rounded border-gray-500 bg-gray-900 text-green-600">
                            Autogol
                        </label>
                        @foreach (['', '.jugador_id', '.tipo', '.minuto', '.autogol'] as $sufijo)
                            @error("eventos.$fila$sufijo") <p class="sm:col-span-12 text-sm text-red-300">{{ $message }}</p> @enderror
                        @endforeach
                    </div>
                @endfor
            </div>
        </fieldset>

        <fieldset class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
            <legend class="px-2 text-lg font-semibold text-white">Incidencias</legend>
            <div class="space-y-3">
                @for ($fila = 0; $fila < $filasIncidencias; $fila++)
                    <div class="grid gap-2 sm:grid-cols-12 items-start">
                        <div class="sm:col-span-7">
                            <label for="incidencia-{{ $fila }}-descripcion" class="sr-only">Descripción de la incidencia {{ $fila + 1 }}</label>
                            <input id="incidencia-{{ $fila }}-descripcion" name="incidencias[{{ $fila }}][descripcion]" maxlength="1000" placeholder="Incidencia {{ $fila + 1 }}: qué pasó" value="{{ $anterior("incidencias.$fila.descripcion") }}" class="{{ $entrada }}">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="incidencia-{{ $fila }}-minuto" class="sr-only">Minuto de la incidencia {{ $fila + 1 }}</label>
                            <input id="incidencia-{{ $fila }}-minuto" name="incidencias[{{ $fila }}][minuto]" type="number" inputmode="numeric" min="0" max="150" placeholder="Minuto" value="{{ $anterior("incidencias.$fila.minuto") }}" class="{{ $entrada }}">
                        </div>
                        <div class="sm:col-span-3">
                            <label for="incidencia-{{ $fila }}-equipo" class="sr-only">Equipo de la incidencia {{ $fila + 1 }}</label>
                            <select id="incidencia-{{ $fila }}-equipo" name="incidencias[{{ $fila }}][equipo_id]" class="{{ $entrada }}">
                                <option value="">Sin equipo</option>
                                @foreach ($equipos as $equipoId => $equipoNombre)
                                    <option value="{{ $equipoId }}" @selected($anterior("incidencias.$fila.equipo_id") === (string) $equipoId)>{{ $equipoNombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        @foreach (['', '.descripcion', '.minuto', '.equipo_id'] as $sufijo)
                            @error("incidencias.$fila$sufijo") <p class="sm:col-span-12 text-sm text-red-300">{{ $message }}</p> @enderror
                        @endforeach
                    </div>
                @endfor
            </div>
        </fieldset>

        <fieldset class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
            <legend class="px-2 text-lg font-semibold text-white">Reporte arbitral</legend>
            <label for="informe_arbitral" class="sr-only">Reporte arbitral</label>
            <textarea id="informe_arbitral" name="informe_arbitral" rows="4" maxlength="5000" class="{{ $entrada }}">{{ $anterior('informe_arbitral', $cedula?->informe_arbitral ?? '') }}</textarea>
            @error('informe_arbitral') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
        </fieldset>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="min-h-12 px-6 py-3 text-base font-semibold text-white bg-green-600 hover:bg-green-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">Guardar cédula</button>
            <a href="{{ route('cedulas.fisicas.index') }}" class="inline-flex items-center min-h-12 px-5 py-3 text-base font-semibold text-gray-200 bg-gray-700 hover:bg-gray-600 rounded-lg">Volver</a>
        </div>
    </form>

    {{-- Respuesta 50 del PO: con lo transcrito guardado, el Capturista cierra la cédula con el escaneo del papel firmado --}}
    @if ($cedula?->esDePapel())
        <section id="cierre" aria-labelledby="titulo-cierre" class="mt-8 bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
            <h2 id="titulo-cierre" class="text-lg font-semibold text-white">Cerrar la cédula en papel</h2>
            <p class="text-sm text-gray-300 mt-2">Antes de cerrar, compara lo guardado con el papel. Al cerrar, la cédula ya no se puede modificar, el partido pasa a «finalizado» y, si hay tarjetas o incidencias, se abre el expediente disciplinario. El escaneo de la cédula firmada por el árbitro y los capitanes sustituye a las firmas digitales.</p>

            @if ($errors->cierre->any())
                <div role="alert" class="mt-4 rounded-lg border border-red-700 bg-red-950 p-4 text-red-100">
                    <p class="font-semibold">No se cerró la cédula:</p>
                    <ul class="mt-2 list-disc pl-5 text-sm">
                        @foreach ($errors->cierre->all() as $mensaje)
                            <li>{{ $mensaje }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('cedulas.fisica.cerrar', $partido) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="cedula_escaneada" class="block text-sm font-medium text-gray-200 mb-1">Foto o escaneo de la cédula en papel firmada (JPG, PNG o PDF, hasta 5 MB)</label>
                    <input id="cedula_escaneada" name="cedula_escaneada" type="file" accept="image/jpeg,image/png,application/pdf" required class="{{ $entrada }}">
                </div>
                <div class="flex items-start gap-3">
                    <input id="confirmar" name="confirmar" type="checkbox" value="1" required class="mt-1 h-6 w-6 rounded border-gray-600 bg-gray-900 text-green-600 focus:ring-2 focus:ring-green-400">
                    <label for="confirmar" class="text-sm text-gray-200">Revisé que lo capturado coincide con la cédula en papel.</label>
                </div>
                <button type="submit" class="min-h-12 px-6 py-3 text-base font-semibold text-white bg-red-700 hover:bg-red-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400">Cerrar la cédula</button>
            </form>
        </section>
    @endif
</div>
@endsection
