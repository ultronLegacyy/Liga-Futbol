@extends('layouts.admin')

@section('title', 'Disponibilidad y Bloqueos de Canchas - SGLD')
@section('header', 'Disponibilidad y Bloqueos de Canchas')

@section('content')
<div class="space-y-6">

    <!-- Navegacion y Selector de Cancha -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-800 border border-gray-700 rounded-xl p-5">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-green-400 transition">Dashboard</a>
                <span>/</span>
                <span class="text-gray-300">Canchas y Campos</span>
                <span>/</span>
                <span class="text-green-400 font-medium">{{ $cancha->nombre }}</span>
                <span>/</span>
                <span class="text-gray-200">Disponibilidad y Bloqueos</span>
            </div>
            <h2 class="text-2xl font-bold text-white flex items-center gap-3">
                <span>{{ $cancha->nombre }}</span>
                @if($cancha->estado !== 'Disponible')
                    <x-badge variant="danger" size="lg">Cancha Inactiva</x-badge>
                @elseif($bloqueoActivoActual)
                    <x-badge variant="warning" size="lg">Bloqueada Actualmente</x-badge>
                @else
                    <x-badge variant="success" size="lg">Disponible para Partidos</x-badge>
                @endif
            </h2>
            <p class="text-sm text-gray-400 mt-1">
                Ubicacion: <span class="text-gray-200">{{ $cancha->direccion_ubicacion ?? 'No especificada' }}</span>
                <span class="mx-2">&bull;</span>
                Capacidad consecutiva: <span class="text-gray-200">{{ $cancha->capacidad_partidos_consecutivos }} partidos</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if($todasCanchas->count() > 1)
                <div class="flex items-center gap-2 text-sm">
                    <label for="selector-cancha" class="text-gray-400 text-xs font-semibold uppercase">Cambiar cancha:</label>
                    <select id="selector-cancha"
                            onchange="window.location.href = this.value"
                            class="bg-gray-900 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                        @foreach($todasCanchas as $otraCancha)
                            <option value="{{ route('canchas.bloqueos.index', $otraCancha) }}" {{ $otraCancha->id === $cancha->id ? 'selected' : '' }}>
                                {{ $otraCancha->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @can('campos.bloquear')
                <button type="button"
                        onclick="abrirModal('modal-nuevo-bloqueo')"
                        class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium text-sm rounded-lg shadow-sm transition flex items-center gap-2">
                    <span class="text-base leading-none font-bold">+</span>
                    <span>Registrar Bloqueo</span>
                </button>
            @endcan
        </div>
    </div>

    <!-- Alertas y Mensajes de Sesion -->
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if(session('error'))
        <x-alert type="error" :message="session('error')" />
    @endif

    @if($errors->any())
        <x-alert type="error">
            <p class="font-semibold mb-1">Por favor corrija los siguientes errores antes de continuar:</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <!-- Tarjetas de Informacion y Comprobador de Disponibilidad -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Tarjeta 1: Estado Operativo -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-5">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Estado Operativo Actual</h3>
            @if($cancha->estado !== 'Disponible')
                <div class="p-3 bg-red-950/40 border border-red-800/40 rounded-lg">
                    <p class="text-sm font-semibold text-red-300">Cancha Fuera de Servicio</p>
                    <p class="text-xs text-red-400 mt-1">
                        El estado de la cancha en el catalogo general es <span class="font-mono">{{ $cancha->estado }}</span>. No se pueden programar partidos hasta su reactivacion.
                    </p>
                </div>
            @elseif($bloqueoActivoActual)
                <div class="p-3 bg-amber-950/40 border border-amber-800/40 rounded-lg">
                    <p class="text-sm font-semibold text-amber-300">Cancha Bloqueada en este Momento</p>
                    <p class="text-xs text-amber-200 mt-1">
                        <strong>Motivo:</strong> {{ $bloqueoActivoActual->motivo }}
                    </p>
                    <p class="text-xs text-amber-300/80 mt-1">
                        Vence: {{ $bloqueoActivoActual->fecha_fin?->format('d/m/Y H:i') }} ({{ $bloqueoActivoActual->fecha_fin?->diffForHumans() }})
                    </p>
                </div>
            @else
                <div class="p-3 bg-green-950/40 border border-green-800/40 rounded-lg">
                    <p class="text-sm font-semibold text-green-300">Cancha Disponible</p>
                    <p class="text-xs text-green-400 mt-1">
                        No hay ningun bloqueo activo en este momento. La cancha esta habilitada para programacion de partidos deportivos.
                    </p>
                </div>
            @endif

            <div class="mt-4 pt-4 border-t border-gray-700/60 grid grid-cols-2 gap-2 text-center text-xs">
                <div class="p-2 bg-gray-900/60 rounded border border-gray-700/40">
                    <span class="block text-gray-400">Vigentes</span>
                    <span class="text-base font-bold text-white">{{ $bloqueosVigentes->count() }}</span>
                </div>
                <div class="p-2 bg-gray-900/60 rounded border border-gray-700/40">
                    <span class="block text-gray-400">Historicos</span>
                    <span class="text-base font-bold text-gray-300">{{ $bloqueosHistoricos->count() }}</span>
                </div>
            </div>
        </div>

        <!-- Tarjeta 2 & 3: Comprobador Interactivo de Disponibilidad en Tiempo Real -->
        <div class="lg:col-span-2 bg-gray-800 border border-gray-700 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                    Comprobador de Disponibilidad en Tiempo Real
                </h3>
                <span class="text-xs text-gray-400">Consulta preventiva antes de programar partidos (RF-05.2)</span>
            </div>

            <form id="form-comprobar-disponibilidad" onsubmit="verificarDisponibilidad(event)" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="check-fecha" class="block text-xs font-medium text-gray-300 mb-1">Fecha</label>
                        <input type="date"
                               id="check-fecha"
                               required
                               value="{{ date('Y-m-d') }}"
                               class="w-full bg-gray-900 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="check-hora-inicio" class="block text-xs font-medium text-gray-300 mb-1">Hora Inicio</label>
                        <input type="time"
                               id="check-hora-inicio"
                               required
                               value="08:00"
                               class="w-full bg-gray-900 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="check-hora-fin" class="block text-xs font-medium text-gray-300 mb-1">Hora Termino</label>
                        <input type="time"
                               id="check-hora-fin"
                               required
                               value="10:00"
                               class="w-full bg-gray-900 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 pt-1">
                    <button type="submit"
                            id="btn-verificar"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs rounded-lg transition flex items-center gap-2">
                        <span>Consultar Disponibilidad</span>
                    </button>
                    <div id="resultado-disponibilidad" class="flex-1 text-xs">
                        <span class="text-gray-400 italic">Seleccione el horario deseado y presione consultar.</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Seccion 1: Bloqueos Vigentes y Programados -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>Bloqueos Vigentes y Programados</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        {{ $bloqueosVigentes->count() }}
                    </span>
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Bloqueos que inhabilitan la cancha en el presente o en fechas futuras.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-900/60 text-gray-400 uppercase text-xs border-b border-gray-700">
                    <tr>
                        <th class="px-5 py-3 font-semibold">ID</th>
                        <th class="px-5 py-3 font-semibold">Inicio de Bloqueo</th>
                        <th class="px-5 py-3 font-semibold">Termino de Bloqueo</th>
                        <th class="px-5 py-3 font-semibold">Estado</th>
                        <th class="px-5 py-3 font-semibold">Motivo del Bloqueo</th>
                        <th class="px-5 py-3 font-semibold">Registrado</th>
                        <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/60">
                    @forelse($bloqueosVigentes as $bloqueo)
                        @php
                            $enCurso = $bloqueo->fecha_inicio?->isPast() && $bloqueo->fecha_fin?->isFuture();
                        @endphp
                        <tr class="hover:bg-gray-750 transition-colors {{ $enCurso ? 'bg-amber-950/20' : '' }}">
                            <td class="px-5 py-3.5 font-mono text-xs text-gray-400">#{{ $bloqueo->id }}</td>
                            <td class="px-5 py-3.5 font-medium text-white whitespace-nowrap">
                                {{ $bloqueo->fecha_inicio?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-3.5 font-medium text-white whitespace-nowrap">
                                {{ $bloqueo->fecha_fin?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if($enCurso)
                                    <x-badge variant="warning">En Curso</x-badge>
                                @else
                                    <x-badge variant="info">Programado</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-gray-200 max-w-xs truncate" title="{{ $bloqueo->motivo }}">
                                {{ $bloqueo->motivo }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-gray-400 whitespace-nowrap">
                                {{ $bloqueo->created_at?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                @can('campos.bloquear')
                                    <button type="button"
                                            onclick="prepararLiberacion(
                                                '{{ $bloqueo->id }}',
                                                '{{ $bloqueo->fecha_inicio?->format('d/m/Y H:i') }}',
                                                '{{ $bloqueo->fecha_fin?->format('d/m/Y H:i') }}',
                                                @js($bloqueo->motivo)
                                            )"
                                            class="px-3 py-1.5 text-xs font-medium text-red-300 hover:text-white bg-red-950/60 hover:bg-red-900 border border-red-800/60 rounded-lg transition">
                                        Liberar Bloqueo
                                    </button>
                                @else
                                    <span class="text-xs text-gray-500 italic">Solo lectura</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                                <p class="text-sm font-medium text-gray-300">No hay bloqueos vigentes ni programados</p>
                                <p class="text-xs text-gray-500 mt-1">La cancha se encuentra completamente habilitada para programacion deportiva.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Seccion 2: Historial de Bloqueos Finalizados -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <span>Historial de Bloqueos Finalizados</span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-700 text-gray-300 border border-gray-600">
                        {{ $bloqueosHistoricos->count() }}
                    </span>
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Registro histórico de bloqueos concluidos para fines de consulta y auditoría inmutable.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-900/60 text-gray-400 uppercase text-xs border-b border-gray-700">
                    <tr>
                        <th class="px-5 py-3 font-semibold">ID</th>
                        <th class="px-5 py-3 font-semibold">Periodo de Inhabilitacion</th>
                        <th class="px-5 py-3 font-semibold">Estado</th>
                        <th class="px-5 py-3 font-semibold">Motivo Registrado</th>
                        <th class="px-5 py-3 font-semibold">Fecha de Registro</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/60">
                    @forelse($bloqueosHistoricos as $bloqueo)
                        <tr class="hover:bg-gray-750 transition-colors opacity-80">
                            <td class="px-5 py-3 font-mono text-xs text-gray-500">#{{ $bloqueo->id }}</td>
                            <td class="px-5 py-3 text-xs whitespace-nowrap text-gray-300">
                                {{ $bloqueo->fecha_inicio?->format('d/m/Y H:i') }} &mdash; {{ $bloqueo->fecha_fin?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <x-badge variant="gray">Finalizado</x-badge>
                            </td>
                            <td class="px-5 py-3 text-gray-300 text-xs">
                                {{ $bloqueo->motivo }}
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ $bloqueo->created_at?->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-6 text-center text-gray-500 text-xs">
                                No existen bloqueos historicos archivados para esta cancha.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal 1: Formulario de Alta de Nuevo Bloqueo -->
@can('campos.bloquear')
<x-modal id="modal-nuevo-bloqueo"
         title="Registrar Bloqueo Temporal de Cancha"
         subtitle="Inhabilita el campo durante un intervalo horario. Requiere motivo justificado para auditoria."
         maxWidth="lg">

    <form method="POST" action="{{ route('canchas.bloqueos.store', $cancha) }}" id="form-nuevo-bloqueo" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="fecha_inicio" class="block text-xs font-semibold text-gray-200 uppercase mb-1">
                    Fecha y Hora de Inicio <span class="text-red-400">*</span>
                </label>
                <input type="datetime-local"
                       id="fecha_inicio"
                       name="fecha_inicio"
                       required
                       value="{{ old('fecha_inicio') }}"
                       class="w-full bg-gray-900 border @error('fecha_inicio') border-red-500 @else border-gray-700 @enderror text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                @error('fecha_inicio')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="fecha_fin" class="block text-xs font-semibold text-gray-200 uppercase mb-1">
                    Fecha y Hora de Termino <span class="text-red-400">*</span>
                </label>
                <input type="datetime-local"
                       id="fecha_fin"
                       name="fecha_fin"
                       required
                       value="{{ old('fecha_fin') }}"
                       class="w-full bg-gray-900 border @error('fecha_fin') border-red-500 @else border-gray-700 @enderror text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                @error('fecha_fin')
                    <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="motivo" class="block text-xs font-semibold text-gray-200 uppercase mb-1">
                Motivo Justificado del Bloqueo <span class="text-red-400">*</span>
            </label>
            <textarea id="motivo"
                      name="motivo"
                      rows="3"
                      required
                      minlength="3"
                      maxlength="255"
                      placeholder="Describa la causa operativa (ej. Mantenimiento de cesped, anegamiento por lluvias torrenciales, reparacion de porterias, evento deportivo ajeno)..."
                      class="w-full bg-gray-900 border @error('motivo') border-red-500 @else border-gray-700 @enderror text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">{{ old('motivo') }}</textarea>
            @error('motivo')
                <p class="text-xs text-red-400 mt-1">{{ $message }}</p>
            @else
                <p class="text-xs text-gray-400 mt-1">Minimo 3 caracteres, maximo 255 caracteres.</p>
            @enderror
        </div>

        <div class="p-3 bg-gray-900/60 border border-gray-700/60 rounded-lg text-xs text-gray-400">
            <span class="font-bold text-gray-300">Auditoría inmutable:</span>
            El registro de este bloqueo se asociará a su cuenta de usuario y fecha en la bitácora oficial. No se permiten traslapes con otros bloqueos de la misma cancha.
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-700">
            <button type="button"
                    onclick="cerrarModal('modal-nuevo-bloqueo')"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg transition">
                Cancelar
            </button>
            <button type="submit"
                    class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                Guardar Bloqueo
            </button>
        </div>
    </form>
</x-modal>

<!-- Modal 2: Confirmacion de Liberacion / Cancelacion de Bloqueo -->
<x-modal id="modal-liberar-bloqueo"
         title="Confirmar Liberacion de Bloqueo"
         subtitle="El retiro del bloqueo volvera a habilitar la cancha para partidos oficiales. Requiere confirmacion y motivo de auditoria."
         maxWidth="md">

    <form method="POST" id="form-liberar-bloqueo" action="" class="space-y-4">
        @csrf
        @method('DELETE')

        <div class="p-3 bg-red-950/30 border border-red-800/40 rounded-lg space-y-1 text-xs text-gray-300">
            <p><strong>Cancha:</strong> {{ $cancha->nombre }}</p>
            <p><strong>Bloqueo a retirar:</strong> <span id="liberar-detalle-bloqueo" class="text-white font-mono">-</span></p>
            <p><strong>Motivo original:</strong> <span id="liberar-motivo-original" class="text-gray-300 italic">-</span></p>
        </div>

        <div>
            <label for="motivo_liberacion" class="block text-xs font-semibold text-gray-200 uppercase mb-1">
                Motivo de la Liberacion / Cancelacion <span class="text-red-400">*</span>
            </label>
            <textarea id="motivo_liberacion"
                      name="motivo"
                      rows="2"
                      required
                      minlength="3"
                      maxlength="255"
                      placeholder="Explique la causa del retiro (ej. Trabajos de mantenimiento concluidos satisfactoriamente antes de tiempo)..."
                      class="w-full bg-gray-900 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2 focus:ring-red-500 focus:border-red-500"></textarea>
            <p class="text-xs text-gray-400 mt-1">Este motivo quedará registrado en la bitácora de auditoría.</p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-700">
            <button type="button"
                    onclick="cerrarModal('modal-liberar-bloqueo')"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg transition">
                Conservar Bloqueo
            </button>
            <button type="submit"
                    class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                Confirmar Liberacion
            </button>
        </div>
    </form>
</x-modal>
@endcan

@endsection

@push('scripts')
<script>
    let ultimoElementoActivo = null;

    function abrirModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            ultimoElementoActivo = document.activeElement;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            setTimeout(() => {
                const primerInput = modal.querySelector('input:not([type="hidden"]), textarea, select');
                if (primerInput) {
                    primerInput.focus();
                } else {
                    const primerBoton = modal.querySelector('button');
                    if (primerBoton) primerBoton.focus();
                }
            }, 50);
        }
    }

    function cerrarModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');

            if (ultimoElementoActivo && typeof ultimoElementoActivo.focus === 'function') {
                ultimoElementoActivo.focus();
            }
        }
    }

    function prepararLiberacion(id, inicio, fin, motivoOriginal) {
        const form = document.getElementById('form-liberar-bloqueo');
        if (form) {
            form.action = "{{ url('/canchas/' . $cancha->id . '/bloqueos') }}/" + id;
        }

        const detalle = document.getElementById('liberar-detalle-bloqueo');
        if (detalle) {
            detalle.textContent = '#' + id + ' (' + inicio + ' a ' + fin + ')';
        }

        const original = document.getElementById('liberar-motivo-original');
        if (original) {
            original.textContent = motivoOriginal || 'Sin motivo previo especificado';
        }

        const inputMotivo = document.getElementById('motivo_liberacion');
        if (inputMotivo) {
            inputMotivo.value = '';
        }

        abrirModal('modal-liberar-bloqueo');
    }

    async function verificarDisponibilidad(event) {
        event.preventDefault();

        const btn = document.getElementById('btn-verificar');
        const resultado = document.getElementById('resultado-disponibilidad');
        const fecha = document.getElementById('check-fecha').value;
        const horaInicio = document.getElementById('check-hora-inicio').value;
        const horaFin = document.getElementById('check-hora-fin').value;

        if (!fecha || !horaInicio || !horaFin) {
            resultado.innerHTML = '<span class="text-amber-400">Complete fecha, hora de inicio y hora de termino.</span>';
            return;
        }

        btn.disabled = true;
        btn.classList.add('opacity-75');
        resultado.innerHTML = '<span class="text-gray-400">Consultando disponibilidad en servidor...</span>';

        const url = new URL("{{ route('canchas.disponibilidad', $cancha) }}", window.location.origin);
        url.searchParams.set('fecha', fecha);
        url.searchParams.set('hora_inicio', horaInicio);
        url.searchParams.set('hora_fin', horaFin);

        try {
            const respuesta = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!respuesta.ok) {
                const errorData = await respuesta.json().catch(() => ({}));
                let mensajeError = errorData.message || 'Error al validar el rango solicitado.';
                if (errorData.errors) {
                    const primerErrorKey = Object.keys(errorData.errors)[0];
                    if (primerErrorKey && errorData.errors[primerErrorKey][0]) {
                        mensajeError = errorData.errors[primerErrorKey][0];
                    }
                }
                resultado.innerHTML = '<span class="text-red-400 font-semibold">' + mensajeError + '</span>';
                return;
            }

            const data = await respuesta.json();

            if (data.disponible) {
                resultado.innerHTML = '<span class="px-2.5 py-1 rounded-md bg-green-500/20 text-green-300 border border-green-500/30 font-medium">Disponible: El campo esta libre para programar partidos en este horario.</span>';
            } else {
                resultado.innerHTML = '<span class="px-2.5 py-1 rounded-md bg-red-500/20 text-red-300 border border-red-500/30 font-medium">No disponible: Existe un bloqueo o la cancha no esta operativa en ese periodo.</span>';
            }
        } catch (error) {
            resultado.innerHTML = '<span class="text-red-400">No se pudo verificar la disponibilidad. Intente nuevamente.</span>';
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-75');
        }
    }

    // Gestion de teclado accesible en modales (Escape para cerrar, Tab para retener foco dentro del modal)
    document.addEventListener('keydown', function(event) {
        const modales = Array.from(document.querySelectorAll('[role="dialog"]'));
        const modalAbierto = modales.find(m => !m.classList.contains('hidden'));

        if (!modalAbierto) return;

        if (event.key === 'Escape') {
            cerrarModal(modalAbierto.id);
            return;
        }

        if (event.key === 'Tab') {
            const focusables = Array.from(modalAbierto.querySelectorAll('button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
            if (focusables.length === 0) return;

            const primerElemento = focusables[0];
            const ultimoElemento = focusables[focusables.length - 1];

            if (event.shiftKey && document.activeElement === primerElemento) {
                event.preventDefault();
                ultimoElemento.focus();
            } else if (!event.shiftKey && document.activeElement === ultimoElemento) {
                event.preventDefault();
                primerElemento.focus();
            }
        }
    });

    // Cerrar al hacer click fuera del contenido del modal
    document.querySelectorAll('[role="dialog"]').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                cerrarModal(modal.id);
            }
        });
    });

    // Si hubo errores de validacion en la peticion anterior, desplegar el modal automaticamente
    @if($errors->any())
        document.addEventListener('DOMContentLoaded', function() {
            abrirModal('modal-nuevo-bloqueo');
        });
    @endif
</script>
@endpush
