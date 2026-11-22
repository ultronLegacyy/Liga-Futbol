@extends('layouts.admin')

@section('title', 'Firmas y cierre de la cédula - SGLD')
@section('header', 'Firmas y cierre')

@section('content')
@php
    $nombre = fn ($jugador) => $jugador?->persona ? $jugador->persona->apellidos.', '.$jugador->persona->nombre : 'Sin jugador';
    $equipos = [$partido->equipo_local_id => $partido->local?->nombre, $partido->equipo_visitante_id => $partido->visitante?->nombre];
    $boton = 'min-h-12 px-4 py-3 text-base font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400';
    $faltanFirmas = collect($firmas)->contains(fn ($firma) => ! $firma['firmada']);
    $listo = ! $faltanFirmas && $hallazgos === [];
@endphp

<div class="max-w-3xl space-y-6" id="cierre-cedula" data-usuario="{{ auth()->id() }}" data-partido="{{ $partido->id }}">
    <div>
        <h2 class="text-xl font-bold text-white">{{ $partido->local?->nombre }} vs. {{ $partido->visitante?->nombre }}</h2>
        <p class="text-sm text-gray-400">
            Partido {{ $partido->id }} · {{ $partido->fecha_partido?->format('d/m/Y') ?? 'Sin fecha' }} · {{ $partido->cancha?->nombre ?? 'Sin cancha' }}
        </p>
        <a href="{{ route('cedulas.show', $partido) }}" class="inline-block mt-2 text-sm text-gray-300 underline">Volver a la captura</a>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if ($errors->any())
        <div role="alert" class="bg-red-500/10 border border-red-500/30 text-red-300 rounded-xl p-4 text-sm">
            <p class="font-semibold mb-1">No se pudo completar:</p>
            <ul class="list-disc ml-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p id="aviso-pendientes" role="alert" class="bg-amber-500/10 border border-amber-500/30 text-amber-300 rounded-xl p-4 text-sm" hidden>
        Hay cambios de la captura sin enviar en este celular. Vuelve a la captura y espera a que diga «Todo guardado» antes de firmar y cerrar.
    </p>

    {{-- Resumen para confirmar (criterio 4) --}}
    <section aria-labelledby="titulo-resumen" class="bg-gray-800 border border-gray-700 rounded-xl p-4 space-y-4">
        <h3 id="titulo-resumen" class="text-lg font-semibold text-white">Resumen</h3>

        <dl class="grid grid-cols-2 gap-4 text-center">
            <div class="bg-gray-900 rounded-lg p-3">
                <dt class="text-sm text-gray-400">{{ $partido->local?->nombre }}</dt>
                <dd class="text-3xl font-bold text-white">{{ $cedula->goles_local }}</dd>
            </div>
            <div class="bg-gray-900 rounded-lg p-3">
                <dt class="text-sm text-gray-400">{{ $partido->visitante?->nombre }}</dt>
                <dd class="text-3xl font-bold text-white">{{ $cedula->goles_visitante }}</dd>
            </div>
        </dl>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($equipos as $equipoId => $equipoNombre)
                @php($asistentes = $cedula->alineaciones->where('equipo_id', $equipoId)->where('asistio', true))
                <div>
                    <h4 class="font-semibold text-gray-200">{{ $equipoNombre }}: {{ $asistentes->count() === 1 ? '1 asistente' : $asistentes->count().' asistentes' }}</h4>
                    <ul class="text-sm text-gray-300">
                        @foreach ($asistentes as $fila)
                            <li>{{ $fila->numero_camiseta ?? '—' }} · {{ $nombre($fila->jugador) }}{{ $fila->es_capitan ? ' (C)' : '' }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div>
            <h4 class="font-semibold text-gray-200">Goles y tarjetas</h4>
            @if ($cedula->eventos->isEmpty())
                <p class="text-sm text-gray-400">Sin goles ni tarjetas.</p>
            @else
                <ul class="text-sm text-gray-300">
                    @foreach ($cedula->eventos as $evento)
                        <li>{{ $evento->minuto !== null ? $evento->minuto."'" : '—' }} · {{ $evento->tipo }}{{ $evento->autogol ? ' (autogol)' : '' }} · {{ $nombre($evento->jugador) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h4 class="font-semibold text-gray-200">Incidencias</h4>
            @if ($cedula->incidencias->isEmpty())
                <p class="text-sm text-gray-400">Sin incidencias.</p>
            @else
                <ul class="text-sm text-gray-300">
                    @foreach ($cedula->incidencias as $incidencia)
                        <li>{{ $incidencia->minuto !== null ? $incidencia->minuto."'" : '—' }} · {{ $incidencia->descripcion }}{{ $incidencia->equipo ? ' · '.$incidencia->equipo->nombre : '' }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h4 class="font-semibold text-gray-200">Reporte arbitral</h4>
            <p class="text-sm text-gray-300 whitespace-pre-line">{{ $cedula->informe_arbitral ?? 'Sin reporte.' }}</p>
        </div>
    </section>

    {{-- Validaciones antes de cerrar (criterio 4, RNF-16) --}}
    <section aria-labelledby="titulo-validacion" class="bg-gray-800 border border-gray-700 rounded-xl p-4">
        <h3 id="titulo-validacion" class="text-lg font-semibold text-white mb-2">Revisión</h3>
        @if ($hallazgos === [])
            <p class="text-sm text-green-400">No se encontraron problemas.</p>
        @else
            <p class="text-sm text-red-300 mb-2">Corrige esto en la captura antes de cerrar:</p>
            <ul class="list-disc ml-5 text-sm text-red-300 space-y-1">
                @foreach ($hallazgos as $hallazgo)
                    <li>{{ $hallazgo['mensaje'] }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Firmas de los capitanes (criterio 1); cualquier cambio posterior las borra --}}
    <section aria-labelledby="titulo-firmas" class="bg-gray-800 border border-gray-700 rounded-xl p-4 space-y-6">
        <div>
            <h3 id="titulo-firmas" class="text-lg font-semibold text-white">Firmas de los capitanes</h3>
            <p class="text-sm text-gray-400">Firma con el dedo en el recuadro. Si después cambia algo de la cédula, las dos firmas se borran y hay que volver a firmar.</p>
        </div>

        @foreach ($firmas as $lado => $firma)
            <div>
                <h4 class="font-semibold text-gray-200">
                    {{ $firma['equipo'] }}:
                    {{ $firma['capitan'] ? 'capitán '.$nombre($firma['capitan']->jugador) : 'sin capitán marcado' }}
                </h4>

                @if (! $firma['capitan'])
                    <p class="text-sm text-amber-300">Marca al capitán de {{ $firma['equipo'] }} en la alineación antes de su firma.</p>
                @else
                    @if ($firma['firmada'])
                        <p class="text-sm text-green-400 mb-2">Firmada.</p>
                        <img src="{{ route('cedulas.firma', [$partido, $lado]) }}" alt="Firma del capitán de {{ $firma['equipo'] }}" class="bg-white rounded-lg max-h-32 mb-2">
                    @endif

                    <form method="POST" action="{{ route('cedulas.firmar', [$partido, $lado]) }}" class="space-y-2" data-firma>
                        @csrf
                        <input type="hidden" name="firma" value="">
                        <canvas class="w-full h-44 bg-white rounded-lg touch-none" role="img" aria-label="Recuadro para la firma del capitán de {{ $firma['equipo'] }}"></canvas>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="{{ $boton }} text-white bg-gray-700 hover:bg-gray-600" data-borrar>Borrar</button>
                            <button type="submit" class="{{ $boton }} text-white bg-green-600 hover:bg-green-500 disabled:opacity-50" disabled data-guardar>
                                {{ $firma['firmada'] ? 'Volver a firmar' : 'Guardar firma' }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    </section>

    {{-- Cierre (criterio 2): sin las dos firmas o con problemas, no se puede cerrar --}}
    <form method="POST" action="{{ route('cedulas.cerrar', $partido) }}" class="bg-gray-800 border border-gray-700 rounded-xl p-4 space-y-4" id="form-cerrar">
        @csrf
        <label class="flex items-start gap-3 text-sm text-gray-200">
            <input type="checkbox" name="confirmar" value="1" class="w-6 h-6 mt-0.5" required @disabled(! $listo)>
            Revisé el resumen con los dos capitanes. Al cerrar, la cédula ya no se puede modificar.
        </label>
        @if ($faltanFirmas)
            <p class="text-sm text-amber-300">Faltan firmas: no se puede cerrar.</p>
        @endif
        <button type="submit" class="{{ $boton }} w-full text-white bg-red-700 hover:bg-red-600 disabled:opacity-50" @disabled(! $listo) data-cerrar>Cerrar cédula</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
// Firmas con el dedo (HU-075): cada recuadro produce un PNG; sin trazos no se guarda
(function () {
    const raiz = document.getElementById('cierre-cedula');

    document.querySelectorAll('form[data-firma]').forEach((formulario) => {
        const lienzo = formulario.querySelector('canvas');
        const contexto = lienzo.getContext('2d');
        const guardar = formulario.querySelector('[data-guardar]');
        let dibujando = false;
        let trazos = 0;

        function preparar() {
            lienzo.width = lienzo.clientWidth;
            lienzo.height = lienzo.clientHeight;
            contexto.fillStyle = '#ffffff';
            contexto.fillRect(0, 0, lienzo.width, lienzo.height);
            contexto.strokeStyle = '#111827';
            contexto.lineWidth = 3;
            contexto.lineCap = 'round';
            contexto.lineJoin = 'round';
            trazos = 0;
            guardar.disabled = true;
        }

        function punto(evento) {
            const caja = lienzo.getBoundingClientRect();
            return [evento.clientX - caja.left, evento.clientY - caja.top];
        }

        lienzo.addEventListener('pointerdown', (evento) => {
            dibujando = true;
            lienzo.setPointerCapture(evento.pointerId);
            const [x, y] = punto(evento);
            contexto.beginPath();
            contexto.moveTo(x, y);
        });
        lienzo.addEventListener('pointermove', (evento) => {
            if (!dibujando) {
                return;
            }
            const [x, y] = punto(evento);
            contexto.lineTo(x, y);
            contexto.stroke();
            trazos++;
            guardar.disabled = trazos < 5 || guardar.dataset.bloqueado === '1';
        });
        ['pointerup', 'pointercancel'].forEach((tipo) => lienzo.addEventListener(tipo, () => { dibujando = false; }));

        formulario.querySelector('[data-borrar]').addEventListener('click', preparar);
        formulario.addEventListener('submit', (evento) => {
            if (trazos < 5) {
                evento.preventDefault();
                return;
            }
            formulario.querySelector('input[name="firma"]').value = lienzo.toDataURL('image/png');
        });

        preparar();
    });

    // Si la captura dejó cambios sin enviar en este celular, no se firma ni se cierra hasta enviarlos
    let pendientes = 0;
    try {
        const cola = JSON.parse(localStorage.getItem('sgld-cedula-' + raiz.dataset.usuario + '-' + raiz.dataset.partido) || '[]');
        pendientes = Array.isArray(cola) ? cola.length : 0;
    } catch (e) {
        pendientes = 0;
    }

    if (pendientes > 0) {
        document.getElementById('aviso-pendientes').hidden = false;
        document.querySelectorAll('[data-cerrar], [data-guardar], #form-cerrar input[name="confirmar"]').forEach((control) => {
            control.disabled = true;
            control.dataset.bloqueado = '1';
        });
        document.querySelectorAll('form[data-firma], #form-cerrar').forEach((formulario) => formulario.addEventListener('submit', (evento) => evento.preventDefault()));
    }
})();
</script>
@endpush
