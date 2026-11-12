@extends('layouts.admin')

@section('title', 'Captura de la cédula - SGLD')
@section('header', 'Cédula arbitral')

@section('content')
@php
    $nombre = fn ($jugador) => $jugador?->persona ? $jugador->persona->apellidos.', '.$jugador->persona->nombre : 'Sin jugador';
    $equipos = [$partido->equipo_local_id => $partido->local?->nombre, $partido->equipo_visitante_id => $partido->visitante?->nombre];
    $jugadores = $cedula->alineaciones->whereNotNull('jugador_id')->mapWithKeys(fn ($fila) => [
        $fila->jugador_id => ['nombre' => $nombre($fila->jugador), 'equipo_id' => $fila->equipo_id],
    ]);
    $boton = 'min-h-12 px-4 py-3 text-base font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400';
    $campo = 'min-h-12 px-3 py-2 text-base text-white bg-gray-900 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

<div class="max-w-3xl space-y-6" id="captura-cedula"
     data-usuario="{{ auth()->id() }}"
     data-partido="{{ $partido->id }}"
     data-url-alineacion="{{ route('cedulas.alineacion', [$partido, 0]) }}"
     data-url-marcador="{{ route('cedulas.marcador', $partido) }}"
     data-url-eventos="{{ route('cedulas.eventos', $partido) }}"
     data-url-incidencias="{{ route('cedulas.incidencias', $partido) }}">

    <div>
        <h2 class="text-xl font-bold text-white">{{ $partido->local?->nombre }} vs. {{ $partido->visitante?->nombre }}</h2>
        <p class="text-sm text-gray-400">
            Partido {{ $partido->id }} · {{ $partido->fecha_partido?->format('d/m/Y') ?? 'Sin fecha' }}
            {{ $partido->hora_partido ? '· '.substr((string) $partido->hora_partido, 0, 5) : '' }}
            · {{ $partido->cancha?->nombre ?? 'Sin cancha' }}
        </p>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if ($errors->any())
        <x-alert type="error" :message="$errors->first()" />
    @endif

    @if ($jugadoresNuevos > 0)
        <form method="POST" action="{{ route('cedulas.abrir', $partido) }}" class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4">
            @csrf
            <p class="text-amber-300 mb-3">{{ $jugadoresNuevos === 1 ? 'Hay 1 jugador nuevo' : 'Hay '.$jugadoresNuevos.' jugadores nuevos' }} en la plantilla que no están en la alineación.</p>
            <button type="submit" class="{{ $boton }} w-full sm:w-auto text-black bg-amber-400 hover:bg-amber-300">Agregarlos a la alineación</button>
        </form>
    @endif

    {{-- Estado del envío: cuántos cambios faltan y el botón para reintentar (RNF-21) --}}
    <div class="sticky top-0 z-30 bg-gray-900/95 border border-gray-700 rounded-xl p-3 flex items-center justify-between gap-3">
        <p id="estado-envio" role="status" aria-live="polite" class="text-sm text-green-400">Todo guardado</p>
        <button type="button" id="reintentar" class="{{ $boton }} text-white bg-gray-700 hover:bg-gray-600" hidden>Reintentar</button>
    </div>
    <p id="error-envio" role="alert" class="text-sm text-red-400" hidden></p>

    {{-- Marcador --}}
    <section aria-labelledby="titulo-marcador" class="bg-gray-800 border border-gray-700 rounded-xl p-4">
        <h3 id="titulo-marcador" class="text-lg font-semibold text-white mb-3">Marcador</h3>
        <div class="grid grid-cols-2 gap-4">
            @foreach (['local' => [$partido->local?->nombre, $cedula->goles_local], 'visitante' => [$partido->visitante?->nombre, $cedula->goles_visitante]] as $lado => [$equipoNombre, $goles])
                <div class="text-center">
                    <label for="goles-{{ $lado }}" class="block text-sm text-gray-300 mb-2">{{ $equipoNombre }}</label>
                    <div class="flex items-center justify-center gap-2">
                        <button type="button" class="{{ $boton }} w-12 text-white bg-gray-700 hover:bg-gray-600" data-goles="{{ $lado }}" data-paso="-1" aria-label="Restar un gol a {{ $equipoNombre }}">−</button>
                        <input type="number" id="goles-{{ $lado }}" class="{{ $campo }} w-16 text-center text-2xl font-bold" min="0" max="99" inputmode="numeric" value="{{ $goles }}">
                        <button type="button" class="{{ $boton }} w-12 text-white bg-gray-700 hover:bg-gray-600" data-goles="{{ $lado }}" data-paso="1" aria-label="Sumar un gol a {{ $equipoNombre }}">+</button>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Alineación precargada: el árbitro marca asistencia, número y capitán, sin escribir nombres --}}
    <section aria-labelledby="titulo-alineacion" class="bg-gray-800 border border-gray-700 rounded-xl p-4">
        <h3 id="titulo-alineacion" class="text-lg font-semibold text-white mb-3">Alineación</h3>
        <div class="space-y-6">
            @foreach ($equipos as $equipoId => $equipoNombre)
                @php($filas = $cedula->alineaciones->where('equipo_id', $equipoId)->whereNotNull('jugador_id'))
                <fieldset>
                    <legend class="font-semibold text-gray-200 mb-2">{{ $equipoNombre }}</legend>
                    @if ($filas->isEmpty())
                        <p class="text-sm text-gray-400">El equipo no tiene jugadores en su plantilla.</p>
                    @else
                        <ul class="divide-y divide-gray-700">
                            @foreach ($filas as $fila)
                                <li class="py-3 flex flex-wrap items-center gap-3" data-alineacion="{{ $fila->jugador_id }}">
                                    <span class="flex-1 min-w-40 text-white">
                                        {{ $nombre($fila->jugador) }}
                                        @if ($fila->jugador && $fila->jugador->estado_elegibilidad !== 'Activo')
                                            <span class="text-xs text-amber-400">({{ $fila->jugador->estado_elegibilidad }})</span>
                                        @endif
                                    </span>
                                    <label class="flex items-center gap-2 text-sm text-gray-300">
                                        Núm.
                                        <input type="number" class="{{ $campo }} w-20" min="0" max="999" inputmode="numeric" data-campo="numero_camiseta" value="{{ $fila->numero_camiseta }}" aria-label="Número de camiseta de {{ $nombre($fila->jugador) }}">
                                    </label>
                                    <label class="flex items-center gap-2 min-h-12 px-3 rounded-lg bg-gray-900 text-sm text-gray-200 cursor-pointer">
                                        <input type="checkbox" class="w-6 h-6" data-campo="asistio" @checked($fila->asistio)>
                                        Asistió
                                    </label>
                                    <label class="flex items-center gap-2 min-h-12 px-3 rounded-lg bg-gray-900 text-sm text-gray-200 cursor-pointer">
                                        <input type="radio" class="w-6 h-6" name="capitan-{{ $equipoId }}" data-campo="es_capitan" @checked($fila->es_capitan)>
                                        Capitán
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </fieldset>
            @endforeach
        </div>
    </section>

    {{-- Goles y tarjetas: se elige al jugador de la alineación; la expulsión es la Roja --}}
    <section aria-labelledby="titulo-eventos" class="bg-gray-800 border border-gray-700 rounded-xl p-4">
        <h3 id="titulo-eventos" class="text-lg font-semibold text-white mb-3">Goles y tarjetas</h3>
        <form id="form-evento" class="space-y-4">
            <div>
                <label for="evento-jugador" class="block text-sm text-gray-300 mb-1">Jugador</label>
                <select id="evento-jugador" name="jugador_id" class="{{ $campo }} w-full" required>
                    <option value="">Elige al jugador</option>
                    @foreach ($equipos as $equipoId => $equipoNombre)
                        <optgroup label="{{ $equipoNombre }}">
                            @foreach ($cedula->alineaciones->where('equipo_id', $equipoId)->whereNotNull('jugador_id') as $fila)
                                <option value="{{ $fila->jugador_id }}">{{ $fila->numero_camiseta !== null ? $fila->numero_camiseta.' · ' : '' }}{{ $nombre($fila->jugador) }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <fieldset>
                <legend class="block text-sm text-gray-300 mb-1">Evento</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['Gol' => 'bg-green-700', 'Amarilla' => 'bg-yellow-600', 'Roja' => 'bg-red-700'] as $tipo => $color)
                        <label class="flex items-center justify-center gap-2 min-h-12 rounded-lg {{ $color }} text-white font-semibold cursor-pointer has-[:checked]:ring-4 has-[:checked]:ring-white">
                            <input type="radio" name="tipo" value="{{ $tipo }}" class="w-5 h-5" required>
                            {{ $tipo }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="evento-minuto" class="block text-sm text-gray-300 mb-1">Minuto (opcional)</label>
                    <input type="number" id="evento-minuto" name="minuto" class="{{ $campo }} w-24" min="0" max="150" inputmode="numeric">
                </div>
                <label class="flex items-center gap-2 min-h-12 px-3 rounded-lg bg-gray-900 text-sm text-gray-200 cursor-pointer">
                    <input type="checkbox" id="evento-autogol" name="autogol" class="w-6 h-6" disabled>
                    Autogol (cuenta para el rival)
                </label>
            </div>
            <button type="submit" class="{{ $boton }} w-full text-white bg-green-600 hover:bg-green-500">Registrar evento</button>
        </form>

        <ul id="lista-eventos" class="divide-y divide-gray-700 text-sm mt-4">
            @foreach ($cedula->eventos as $evento)
                <li class="py-2 text-white">
                    <span class="inline-block w-12 text-gray-400">{{ $evento->minuto !== null ? $evento->minuto."'" : '—' }}</span>
                    {{ $evento->tipo }}{{ $evento->autogol ? ' (autogol)' : '' }} · {{ $nombre($evento->jugador) }}
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Incidencias en texto libre --}}
    <section aria-labelledby="titulo-incidencias" class="bg-gray-800 border border-gray-700 rounded-xl p-4">
        <h3 id="titulo-incidencias" class="text-lg font-semibold text-white mb-3">Incidencias</h3>
        <form id="form-incidencia" class="space-y-4">
            <div>
                <label for="incidencia-descripcion" class="block text-sm text-gray-300 mb-1">Descripción</label>
                <textarea id="incidencia-descripcion" name="descripcion" rows="3" maxlength="1000" class="{{ $campo }} w-full" required></textarea>
            </div>
            <div class="flex flex-wrap gap-4">
                <div>
                    <label for="incidencia-minuto" class="block text-sm text-gray-300 mb-1">Minuto (opcional)</label>
                    <input type="number" id="incidencia-minuto" name="minuto" class="{{ $campo }} w-24" min="0" max="150" inputmode="numeric">
                </div>
                <div class="flex-1 min-w-40">
                    <label for="incidencia-equipo" class="block text-sm text-gray-300 mb-1">Equipo (opcional)</label>
                    <select id="incidencia-equipo" name="equipo_id" class="{{ $campo }} w-full">
                        <option value="">Ninguno</option>
                        @foreach ($equipos as $equipoId => $equipoNombre)
                            <option value="{{ $equipoId }}">{{ $equipoNombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="{{ $boton }} w-full text-white bg-green-600 hover:bg-green-500">Registrar incidencia</button>
        </form>

        <ul id="lista-incidencias" class="divide-y divide-gray-700 text-sm mt-4">
            @foreach ($cedula->incidencias as $incidencia)
                <li class="py-2 text-white">
                    <span class="inline-block w-12 text-gray-400">{{ $incidencia->minuto !== null ? $incidencia->minuto."'" : '—' }}</span>
                    {{ $incidencia->descripcion }}
                    @if ($incidencia->equipo)
                        <span class="text-gray-400">· {{ $incidencia->equipo->nombre }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection

@push('scripts')
<script>
// Captura de la cédula sin perder datos (HU-036, RNF-21). Cada cambio lleva una clave de envío generada aquí;
// si falla la red, espera en localStorage y se reenvía en orden. El servidor ignora una clave repetida.
(function () {
    const raiz = document.getElementById('captura-cedula');
    const jugadores = @json($jugadores);
    const equipos = @json($equipos);
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const almacen = 'sgld-cedula-' + raiz.dataset.usuario + '-' + raiz.dataset.partido;
    const estado = document.getElementById('estado-envio');
    const reintentar = document.getElementById('reintentar');
    const errorEnvio = document.getElementById('error-envio');
    let enviando = false;

    // Clave UUID v4; crypto.randomUUID solo existe en HTTPS o localhost
    function nuevaClave() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        const b = window.crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    }

    function leerCola() {
        try {
            const cola = JSON.parse(localStorage.getItem(almacen) || '[]');
            return Array.isArray(cola) ? cola : [];
        } catch (e) {
            return [];
        }
    }

    function guardarCola(cola) {
        try {
            localStorage.setItem(almacen, JSON.stringify(cola));
        } catch (e) {
            mostrarError('El navegador no deja guardar los cambios pendientes: no recargues la página hasta que se envíen.');
        }
    }

    function mostrarEstado(texto, clase) {
        const pendientes = leerCola().length;
        estado.textContent = texto || (pendientes === 0 ? 'Todo guardado' : (pendientes === 1 ? '1 cambio por enviar' : pendientes + ' cambios por enviar'));
        estado.className = 'text-sm ' + (clase || (pendientes === 0 ? 'text-green-400' : 'text-amber-300'));
        reintentar.hidden = pendientes === 0;
    }

    function mostrarError(texto) {
        errorEnvio.textContent = texto;
        errorEnvio.hidden = !texto;
    }

    function textoMinuto(minuto) {
        return minuto === null || minuto === undefined || minuto === '' ? '—' : minuto + "'";
    }

    // Agrega a la lista un evento o una incidencia; los pendientes se marcan hasta que el servidor los confirma
    function agregarALista(cambio) {
        const lista = document.getElementById(cambio.tipo === 'evento' ? 'lista-eventos' : 'lista-incidencias');
        const fila = document.createElement('li');
        const minuto = document.createElement('span');
        const marca = document.createElement('span');
        const d = cambio.datos;
        let texto;

        if (cambio.tipo === 'evento') {
            const jugador = jugadores[d.jugador_id];
            texto = ' ' + d.tipo + (d.autogol ? ' (autogol)' : '') + ' · ' + (jugador ? jugador.nombre : 'Sin jugador');
        } else {
            texto = ' ' + d.descripcion + (d.equipo_id ? ' · ' + equipos[d.equipo_id] : '');
        }

        fila.className = 'py-2 text-white';
        fila.dataset.clave = d.clave_envio;
        minuto.className = 'inline-block w-12 text-gray-400';
        minuto.textContent = textoMinuto(d.minuto);
        marca.className = 'ml-2 text-xs text-amber-300';
        marca.textContent = '(por enviar)';
        fila.append(minuto, document.createTextNode(texto), marca);
        lista.append(fila);
    }

    function confirmarEnLista(clave, rechazado) {
        const marca = document.querySelector('li[data-clave="' + clave + '"] span:last-child');

        if (!marca) {
            return;
        }
        if (rechazado) {
            marca.textContent = '(no se guardó)';
            marca.className = 'ml-2 text-xs text-red-400';
        } else {
            marca.remove();
        }
    }

    function encolar(tipo, url, metodo, datos) {
        const cambio = { tipo: tipo, url: url, metodo: metodo, datos: Object.assign({ clave_envio: nuevaClave() }, datos) };
        const cola = leerCola();

        cola.push(cambio);
        guardarCola(cola);

        if (tipo === 'evento' || tipo === 'incidencia') {
            agregarALista(cambio);
        }

        mostrarEstado();
        enviar();
    }

    // Envía los cambios en orden; se detiene en el primero que no llega y lo reintenta después
    async function enviar() {
        if (enviando) {
            return;
        }
        enviando = true;
        let aviso = '';

        try {
            while (leerCola().length > 0) {
                const cambio = leerCola()[0];
                let respuesta;

                try {
                    respuesta = await fetch(cambio.url, {
                        method: cambio.metodo,
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(cambio.datos),
                    });
                } catch (e) {
                    aviso = ' · sin conexión';
                    return;
                }

                if (respuesta.status === 419) {
                    mostrarError('La sesión de la página expiró. Recárgala: los cambios pendientes se enviarán solos.');
                    return;
                }
                if (respuesta.status === 401) {
                    mostrarError('Tu sesión terminó. Inicia sesión otra vez y vuelve a esta cédula: los cambios pendientes se enviarán solos.');
                    return;
                }
                if (respuesta.status >= 500 || respuesta.status === 429) {
                    aviso = ' · el servidor no respondió';
                    return;
                }

                const cola = leerCola();
                cola.shift();
                guardarCola(cola);

                if (respuesta.ok) {
                    confirmarEnLista(cambio.datos.clave_envio, false);
                    mostrarError('');
                } else {
                    // 422 o 403: reenviarlo no lo arregla; se descarta y se avisa
                    let mensaje = 'No se guardó un cambio.';
                    try {
                        const cuerpo = await respuesta.json();
                        const errores = cuerpo.errors ? Object.values(cuerpo.errors).flat() : [];
                        mensaje = 'No se guardó un cambio: ' + (errores[0] || cuerpo.mensaje || cuerpo.message || 'el servidor lo rechazó.');
                    } catch (e) {
                        // Respuesta sin JSON: queda el mensaje general
                    }
                    confirmarEnLista(cambio.datos.clave_envio, true);
                    mostrarError(mensaje + ' Recarga la página para ver lo guardado.');
                }
                mostrarEstado();
            }
        } finally {
            enviando = false;
            mostrarEstado();
            if (aviso && leerCola().length > 0) {
                estado.textContent += aviso;
            }
        }
    }

    // Marcador: cada cambio manda el marcador completo
    function enviarMarcador() {
        const local = document.getElementById('goles-local');
        const visitante = document.getElementById('goles-visitante');
        const golesLocal = Math.min(99, Math.max(0, parseInt(local.value, 10) || 0));
        const golesVisitante = Math.min(99, Math.max(0, parseInt(visitante.value, 10) || 0));

        local.value = golesLocal;
        visitante.value = golesVisitante;
        encolar('marcador', raiz.dataset.urlMarcador, 'PUT', { goles_local: golesLocal, goles_visitante: golesVisitante });
    }

    document.querySelectorAll('[data-goles]').forEach((boton) => boton.addEventListener('click', () => {
        const campo = document.getElementById('goles-' + boton.dataset.goles);
        campo.value = (parseInt(campo.value, 10) || 0) + parseInt(boton.dataset.paso, 10);
        enviarMarcador();
    }));
    ['goles-local', 'goles-visitante'].forEach((id) => document.getElementById(id).addEventListener('change', enviarMarcador));

    // Alineación: asistencia, número y capitán de cada jugador
    document.querySelectorAll('[data-alineacion]').forEach((fila) => {
        const url = raiz.dataset.urlAlineacion.replace(/\/0$/, '/' + fila.dataset.alineacion);

        fila.querySelectorAll('[data-campo]').forEach((control) => control.addEventListener('change', () => {
            const datos = {};

            if (control.dataset.campo === 'numero_camiseta') {
                datos.numero_camiseta = control.value === '' ? null : parseInt(control.value, 10);
            } else if (control.dataset.campo === 'asistio') {
                datos.asistio = control.checked;
            } else if (control.checked) {
                datos.es_capitan = true;
            } else {
                return;
            }

            encolar('alineacion', url, 'PUT', datos);
        }));
    });

    // Goles y tarjetas: el autogol solo aplica a un gol
    const formEvento = document.getElementById('form-evento');
    const autogol = document.getElementById('evento-autogol');

    formEvento.querySelectorAll('input[name="tipo"]').forEach((opcion) => opcion.addEventListener('change', () => {
        autogol.disabled = opcion.value !== 'Gol';
        if (autogol.disabled) {
            autogol.checked = false;
        }
    }));

    formEvento.addEventListener('submit', (evento) => {
        evento.preventDefault();
        const datos = new FormData(formEvento);
        const minuto = datos.get('minuto');

        encolar('evento', raiz.dataset.urlEventos, 'POST', {
            jugador_id: parseInt(datos.get('jugador_id'), 10),
            tipo: datos.get('tipo'),
            minuto: minuto === '' ? null : parseInt(minuto, 10),
            autogol: autogol.checked,
        });
        formEvento.reset();
        autogol.disabled = true;
        document.getElementById('evento-jugador').focus();
    });

    // Incidencias
    const formIncidencia = document.getElementById('form-incidencia');

    formIncidencia.addEventListener('submit', (evento) => {
        evento.preventDefault();
        const datos = new FormData(formIncidencia);
        const minuto = datos.get('minuto');
        const equipo = datos.get('equipo_id');

        encolar('incidencia', raiz.dataset.urlIncidencias, 'POST', {
            descripcion: String(datos.get('descripcion')).trim(),
            minuto: minuto === '' ? null : parseInt(minuto, 10),
            equipo_id: equipo === '' ? null : parseInt(equipo, 10),
        });
        formIncidencia.reset();
        document.getElementById('incidencia-descripcion').focus();
    });

    // Al cargar: muestra en pantalla lo que quedó pendiente de una visita anterior y lo intenta enviar
    leerCola().forEach((cambio) => {
        const d = cambio.datos;

        if (cambio.tipo === 'evento' || cambio.tipo === 'incidencia') {
            agregarALista(cambio);
        } else if (cambio.tipo === 'marcador') {
            document.getElementById('goles-local').value = d.goles_local;
            document.getElementById('goles-visitante').value = d.goles_visitante;
        } else if (cambio.tipo === 'alineacion') {
            const fila = document.querySelector('[data-alineacion="' + cambio.url.split('/').pop() + '"]');
            if (!fila) {
                return;
            }
            if ('asistio' in d) {
                fila.querySelector('[data-campo="asistio"]').checked = d.asistio;
            }
            if ('numero_camiseta' in d) {
                fila.querySelector('[data-campo="numero_camiseta"]').value = d.numero_camiseta === null ? '' : d.numero_camiseta;
            }
            if (d.es_capitan) {
                fila.querySelector('[data-campo="es_capitan"]').checked = true;
            }
        }
    });

    reintentar.addEventListener('click', enviar);
    window.addEventListener('online', enviar);
    setInterval(() => {
        if (leerCola().length > 0) {
            enviar();
        }
    }, 30000);
    mostrarEstado();
    enviar();
})();
</script>
@endpush
