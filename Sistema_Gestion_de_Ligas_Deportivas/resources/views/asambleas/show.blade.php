@extends('layouts.admin')

@section('title', 'Asamblea del '.$asamblea->fecha->format('d/m/Y'))

@section('content')
<main class="mx-auto max-w-5xl p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">Asamblea {{ $asamblea->tipo }} · {{ $asamblea->estado }}</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Asamblea del {{ $asamblea->fecha->format('d/m/Y') }}</h1>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('asambleas.index') }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 hover:bg-gray-700">Volver</a>
            @can('registrarAsistencia', $asamblea)
                <a href="{{ route('asambleas.asistencia.edit', $asamblea) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500">
                    {{ $asamblea->tieneAsistenciaRegistrada() ? 'Corregir asistencia' : 'Registrar asistencia' }}
                </a>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div role="status" class="mb-6 rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-emerald-200">{{ session('success') }}</div>
    @endif

    <section aria-labelledby="convocatoria-titulo" class="mb-6 rounded-xl border border-gray-700 bg-gray-800 p-6">
        <h2 id="convocatoria-titulo" class="mb-4 text-xl font-semibold text-white">Convocatoria</h2>
        <dl class="grid gap-4 text-sm md:grid-cols-2">
            <div><dt class="text-gray-400">Fecha y hora</dt><dd class="text-white">{{ $asamblea->fecha->format('d/m/Y') }} @if ($asamblea->hora) · {{ substr($asamblea->hora, 0, 5) }} @endif</dd></div>
            <div><dt class="text-gray-400">Lugar</dt><dd class="text-white">{{ $asamblea->lugar ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Temporada</dt><dd class="text-white">{{ $asamblea->temporada?->nombre ?? 'Sin temporada' }}</dd></div>
            <div><dt class="text-gray-400">Convocada por</dt><dd class="text-white">{{ $asamblea->convocadaPor?->name ?? '—' }}</dd></div>
        </dl>
        <h3 class="mt-5 text-sm font-semibold text-gray-300">Orden del día</h3>
        <p class="mt-1 whitespace-pre-line text-sm text-gray-100">{{ $asamblea->orden_del_dia ?? '—' }}</p>
    </section>

    <section aria-labelledby="asistencia-titulo" class="rounded-xl border border-gray-700 bg-gray-800 p-6">
        <h2 id="asistencia-titulo" class="mb-4 text-xl font-semibold text-white">Lista de asistencia</h2>
        @if (! $asamblea->tieneAsistenciaRegistrada())
            <p role="status" class="text-sm text-gray-300">La asistencia de los delegados todavía no se ha registrado.</p>
        @else
            <p class="mb-3 text-sm text-gray-300">
                Asistieron {{ $asamblea->asistencias->where('asistio', true)->count() }} de {{ $asamblea->asistencias->count() }} clubes.
            </p>
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <caption class="sr-only">Asistencia por club</caption>
                <thead class="text-left text-xs uppercase tracking-wider text-gray-400">
                    <tr>
                        <th scope="col" class="py-2 pr-4">Club</th>
                        <th scope="col" class="py-2 pr-4">Asistió</th>
                        <th scope="col" class="py-2 pr-4">Representante</th>
                        <th scope="col" class="py-2">Observaciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @foreach ($asamblea->asistencias->sortBy(fn ($asistencia) => $asistencia->club->nombre) as $asistencia)
                        <tr>
                            <td class="py-2 pr-4">{{ $asistencia->club->nombre }}</td>
                            <td class="py-2 pr-4">{{ $asistencia->asistio ? 'Sí' : 'No' }}</td>
                            <td class="py-2 pr-4">{{ $asistencia->nombre_asistente ?? '—' }}</td>
                            <td class="py-2">{{ $asistencia->observaciones ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @error('asamblea') <div role="alert" class="mt-6 rounded-lg border border-red-700 bg-red-950/40 p-4 text-sm text-red-200">{{ $message }}</div> @enderror

    <section aria-labelledby="acuerdos-titulo" class="mt-6 rounded-xl border border-gray-700 bg-gray-800 p-6">
        <h2 id="acuerdos-titulo" class="mb-4 text-xl font-semibold text-white">Acuerdos</h2>
        @if ($asamblea->acuerdosRegistrados->isEmpty())
            <p class="text-sm text-gray-300">No hay acuerdos registrados.</p>
        @else
            <ol class="space-y-4">
                @foreach ($asamblea->acuerdosRegistrados as $acuerdo)
                    <li class="rounded-lg border border-gray-700 bg-gray-900/50 p-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-bold text-white">Acuerdo {{ $acuerdo->numero }}</span>
                            @if ($acuerdo->modifica_reglamento)
                                <span class="rounded bg-amber-900/60 px-2 py-0.5 text-xs font-semibold text-amber-100">Modifica el reglamento de {{ $acuerdo->temporada?->nombre ?? 'la temporada' }}</span>
                            @else
                                <span class="rounded bg-gray-700 px-2 py-0.5 text-xs font-semibold text-gray-200">No modifica el reglamento</span>
                            @endif
                            @if ($acuerdo->es_regla_permanente)
                                <span class="rounded bg-emerald-900/60 px-2 py-0.5 text-xs font-semibold text-emerald-100">Regla permanente · autorizó {{ $acuerdo->autorizadoPor?->name ?? '—' }} el {{ $acuerdo->autorizado_en?->format('d/m/Y') }}</span>
                            @endif
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-100">{{ $acuerdo->descripcion }}</p>
                        @if ($acuerdo->es_regla_permanente && $acuerdo->motivo_autorizacion)
                            <p class="mt-2 text-xs text-gray-400">Motivo de la autorización: {{ $acuerdo->motivo_autorizacion }}</p>
                        @endif
                        @if ($acuerdo->modifica_reglamento && ! $acuerdo->es_regla_permanente)
                            @can('autorizarReglaPermanente', $asamblea)
                                <form method="POST" action="{{ route('asambleas.acuerdos.autorizar', [$asamblea, $acuerdo]) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                    @csrf
                                    <div class="min-w-64 flex-1">
                                        <label for="motivo-{{ $acuerdo->id }}" class="mb-1 block text-xs font-medium text-gray-300">Motivo para volverlo regla permanente</label>
                                        <input id="motivo-{{ $acuerdo->id }}" name="motivo" required minlength="10" maxlength="2000" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-1.5 text-sm text-white">
                                    </div>
                                    <button type="submit" class="rounded-lg bg-amber-700 px-3 py-2 text-xs font-bold text-white hover:bg-amber-600">Autorizar como regla permanente</button>
                                </form>
                            @endcan
                        @endif
                        {{-- Errores de la autorización de este acuerdo: van en su propia bolsa (AutorizarAcuerdoAsambleaRequest). --}}
                        @foreach ($errors->getBag(App\Http\Requests\AutorizarAcuerdoAsambleaRequest::bolsaDeErrores($acuerdo))->all() as $mensaje)
                            <p role="alert" class="mt-2 text-sm text-red-300">Acuerdo {{ $acuerdo->numero }}: {{ $mensaje }}</p>
                        @endforeach
                    </li>
                @endforeach
            </ol>
        @endif

        @can('registrarAcuerdos', $asamblea)
            @if ($asamblea->estado === App\Models\Asamblea::ESTADO_CELEBRADA)
                <form method="POST" action="{{ route('asambleas.acuerdos.store', $asamblea) }}" class="mt-6 space-y-4 border-t border-gray-700 pt-5">
                    @csrf
                    <h3 class="text-lg font-semibold text-white">Registrar acuerdo</h3>
                    <div>
                        <label for="descripcion" class="mb-1 block text-sm font-medium text-gray-200">Acuerdo</label>
                        <textarea id="descripcion" name="descripcion" rows="3" required maxlength="5000" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">{{ old('descripcion') }}</textarea>
                        @error('descripcion') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium text-gray-200">¿Modifica el reglamento de la temporada?</legend>
                        <div class="flex gap-6 text-sm text-gray-100">
                            <label class="flex items-center gap-2"><input type="radio" name="modifica_reglamento" value="0" required @checked(old('modifica_reglamento') === '0')> No</label>
                            <label class="flex items-center gap-2"><input type="radio" name="modifica_reglamento" value="1" @checked(old('modifica_reglamento') === '1')> Sí</label>
                        </div>
                        @error('modifica_reglamento') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </fieldset>
                    <div>
                        <label for="temporada-acuerdo" class="mb-1 block text-sm font-medium text-gray-200">Temporada cuyo reglamento modifica</label>
                        <select id="temporada-acuerdo" name="temporada_id" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                            <option value="">{{ $asamblea->temporada ? 'La de la asamblea ('.$asamblea->temporada->nombre.')' : 'Selecciona temporada' }}</option>
                            @foreach ($temporadas as $temporada)
                                <option value="{{ $temporada->id }}" @selected(old('temporada_id') == $temporada->id)>{{ $temporada->nombre }}</option>
                            @endforeach
                        </select>
                        @error('temporada_id') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end"><button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500">Registrar acuerdo</button></div>
                </form>
            @else
                <p class="mt-4 text-sm text-gray-400">Registra la asistencia para poder capturar los acuerdos y comunicados.</p>
            @endif
        @endcan
    </section>

    <section aria-labelledby="comunicados-titulo" class="mt-6 rounded-xl border border-gray-700 bg-gray-800 p-6">
        <h2 id="comunicados-titulo" class="mb-4 text-xl font-semibold text-white">Comunicados</h2>
        @forelse ($asamblea->comunicados as $comunicado)
            <article class="mb-4 rounded-lg border border-gray-700 bg-gray-900/50 p-4">
                <h3 class="font-semibold text-white">{{ $comunicado->titulo }}</h3>
                <p class="mt-1 text-xs text-gray-400">{{ $comunicado->created_at?->format('d/m/Y H:i') }} · {{ $comunicado->registradoPor?->name }}</p>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-100">{{ $comunicado->contenido }}</p>
            </article>
        @empty
            <p class="text-sm text-gray-300">No hay comunicados registrados.</p>
        @endforelse

        @can('registrarAcuerdos', $asamblea)
            @if ($asamblea->estado === App\Models\Asamblea::ESTADO_CELEBRADA)
                <form method="POST" action="{{ route('asambleas.comunicados.store', $asamblea) }}" class="mt-6 space-y-4 border-t border-gray-700 pt-5">
                    @csrf
                    <h3 class="text-lg font-semibold text-white">Registrar comunicado</h3>
                    <div>
                        <label for="titulo" class="mb-1 block text-sm font-medium text-gray-200">Título</label>
                        <input id="titulo" name="titulo" required maxlength="200" value="{{ old('titulo') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                        @error('titulo') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="contenido" class="mb-1 block text-sm font-medium text-gray-200">Contenido</label>
                        <textarea id="contenido" name="contenido" rows="4" required maxlength="10000" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">{{ old('contenido') }}</textarea>
                        @error('contenido') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end"><button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500">Registrar comunicado</button></div>
                </form>
            @endif
        @endcan
    </section>
</main>
@endsection
