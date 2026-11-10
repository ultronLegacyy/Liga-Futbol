@extends('layouts.admin')

@section('title', 'Ficha del jugador - SGLD')
@section('header', 'Ficha del jugador')

@section('content')
<main class="mx-auto max-w-5xl space-y-6">
    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-semibold">No se pudo completar la operación:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @can('viewAny', App\Models\Jugador::class)
        <a href="{{ route('jugadores.index') }}" class="inline-flex text-sm text-emerald-300 underline">← Volver al listado de jugadores</a>
    @endcan

    <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7" aria-labelledby="jugador-titulo">
        <h2 id="jugador-titulo" class="text-xl font-bold text-white">{{ $jugador->persona->nombre }} {{ $jugador->persona->apellidos }}</h2>
        <p class="mt-1 text-sm text-gray-300">{{ $jugador->equipo->nombre }} · {{ $jugador->equipo->grupo->categoria->nombre }} · {{ $jugador->equipo->temporada->nombre }}</p>
        <p class="mt-4 text-sm text-gray-200">Estado actual: <span class="font-semibold">{{ $jugador->estado_elegibilidad }}</span></p>

        @can('jugadores.crear')
            <a href="{{ route('jugadores.create') }}" class="mt-5 inline-flex rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 hover:bg-gray-700">Registrar otro jugador</a>
        @endcan
    </section>

    <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7" aria-labelledby="documentos-titulo">
        <h2 id="documentos-titulo" class="text-lg font-semibold text-white">Documentos</h2>
        <ul class="mt-3 space-y-2">
            @forelse ($jugador->persona->documentos as $documento)
                <li><a class="text-emerald-300 underline" href="{{ route('jugadores.documentos.show', [$jugador, $documento]) }}">Descargar {{ $documento->tipo }}</a></li>
            @empty
                <li class="text-sm text-gray-300">No hay documentos registrados.</li>
            @endforelse
        </ul>
    </section>

    @if ($jugador->estado_elegibilidad === 'Baja')
        <p class="rounded-lg border border-red-800 bg-red-950/40 p-4 text-sm text-red-100">Baja registrada {{ $jugador->fecha_baja?->format('d/m/Y') }}: {{ $jugador->motivo_baja }}</p>
    @endif
    @if ($jugador->estado_elegibilidad !== 'Baja')
        @can('changeStatus', $jugador)
            <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7" aria-labelledby="cambio-estado-titulo">
                <h2 id="cambio-estado-titulo" class="text-lg font-semibold text-white">Cambiar estado</h2>
                <p class="mt-1 text-sm text-gray-300">El motivo es obligatorio. El cambio quedará en el historial con tu usuario y la fecha.</p>
                <form method="POST" action="{{ route('jugadores.estado.update', $jugador) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="estado_elegibilidad" class="mb-1 block text-sm font-medium text-gray-200">Nuevo estado</label>
                            <select id="estado_elegibilidad" name="estado_elegibilidad" required class="w-full rounded-lg border border-gray-600 bg-gray-900 p-2 text-white">
                                <option value="">Selecciona un estado</option>
                                @foreach (\App\Models\Jugador::ESTADOS_ELEGIBILIDAD as $estado)
                                    @if ($estado !== $jugador->estado_elegibilidad)
                                        <option value="{{ $estado }}" @selected(old('estado_elegibilidad') === $estado)>{{ $estado }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="estado_fecha_baja" class="mb-1 block text-sm font-medium text-gray-200">Fecha de baja (solo para Baja)</label>
                            <input id="estado_fecha_baja" name="fecha_baja" type="date" min="{{ \Illuminate\Support\Carbon::parse($jugador->equipo->grupo->categoria->temporada->fecha_inicio)->toDateString() }}" max="{{ \App\Services\GestionJugadorService::hoy() }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 p-2 text-white">
                        </div>
                    </div>
                    <div>
                        <label for="motivo_estado" class="mb-1 block text-sm font-medium text-gray-200">Motivo</label>
                        <textarea id="motivo_estado" name="motivo" rows="3" required minlength="3" maxlength="1000" class="w-full rounded-lg border border-gray-600 bg-gray-900 p-3 text-white"></textarea>
                    </div>
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-600">Guardar cambio de estado</button>
                </form>
            </section>
        @endcan
        @can('deactivate', $jugador)
            @if ($jugador->estado_elegibilidad === 'Activo')
                <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7">
                    <h2 class="text-lg font-semibold text-white">Dar de baja</h2>
                    <form method="POST" action="{{ route('jugadores.baja', $jugador) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <textarea name="motivo" required minlength="3" maxlength="1000" aria-label="Motivo de baja" class="rounded-lg border border-gray-600 bg-gray-900 p-3 text-white">{{ old('motivo') }}</textarea>
                        <div><input name="fecha_baja" type="date" min="{{ \Illuminate\Support\Carbon::parse($jugador->equipo->grupo->categoria->temporada->fecha_inicio)->toDateString() }}" max="{{ \App\Services\GestionJugadorService::hoy() }}" value="{{ old('fecha_baja') }}" required aria-label="Fecha de baja" class="rounded-lg border border-gray-600 bg-gray-900 p-3 text-white"><button class="ml-3 rounded-lg bg-red-700 px-4 py-2 font-semibold text-white">Confirmar baja</button></div>
                    </form>
                </section>
            @endif
        @endcan
        @can('renew', $jugador)
            <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7">
                <h2 class="text-lg font-semibold text-white">Renovar a otra temporada</h2>
                <form method="POST" action="{{ route('jugadores.renovar', $jugador) }}" class="mt-4 flex flex-wrap gap-3">@csrf
                    <select name="equipo_id" required class="rounded-lg border border-gray-600 bg-gray-900 p-2 text-white"><option value="">Equipo de otra temporada activa</option>@foreach ($equiposRenovacion as $equipo)<option value="{{ $equipo->id }}">{{ $equipo->nombre }} · {{ $equipo->temporada->nombre }} · {{ $equipo->grupo->categoria->nombre }}</option>@endforeach</select>
                    <input name="dorsal" type="number" min="1" max="99" value="{{ $jugador->dorsal }}" aria-label="Dorsal opcional" class="w-24 rounded-lg border border-gray-600 bg-gray-900 p-2 text-white"><button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Renovar</button>
                </form>
            </section>
        @endcan
        @can('transfer', $jugador)
            <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7">
                <h2 class="text-lg font-semibold text-white">Transferir a otro club</h2>
                <p class="mt-1 text-sm text-gray-300"><span class="font-semibold text-white">Transferencia directa.</span> No requiere aprobación del club destino y conserva la inscripción de origen como historial.</p>
                <form method="POST" action="{{ route('jugadores.transferir', $jugador) }}" class="mt-4 flex flex-wrap gap-3">@csrf
                    <select name="equipo_id" required class="rounded-lg border border-gray-600 bg-gray-900 p-2 text-white"><option value="">Equipo de otro club</option>@foreach ($equiposTransferencia as $equipo)<option value="{{ $equipo->id }}">{{ $equipo->nombre }} · {{ $equipo->club->nombre }} · {{ $equipo->temporada->nombre }} · {{ $equipo->grupo->categoria->nombre }}</option>@endforeach</select>
                    <input name="dorsal" type="number" min="1" max="99" value="{{ $jugador->dorsal }}" aria-label="Dorsal opcional" class="w-24 rounded-lg border border-gray-600 bg-gray-900 p-2 text-white"><button class="rounded-lg bg-amber-700 px-4 py-2 font-semibold text-white">Transferir</button>
                </form>
            </section>
        @endcan
    @endif
    @if ($jugador->transferenciasOrigen->isNotEmpty())
        <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7"><h2 class="text-lg font-semibold text-white">Historial de transferencias</h2><ul class="mt-3 space-y-2">@foreach ($jugador->transferenciasOrigen as $transferencia)<li class="text-sm text-gray-200">{{ $transferencia->created_at?->timezone(\App\Services\GestionJugadorService::ZONA_HORARIA)->format('d/m/Y H:i') }} · destino {{ $transferencia->clubDestino->nombre }} / {{ $transferencia->equipoDestino->nombre }}</li>@endforeach</ul></section>
    @endif

    <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7" aria-labelledby="historial-estados-titulo">
        <h2 id="historial-estados-titulo" class="text-lg font-semibold text-white">Historial de estados</h2>
        <ul class="mt-3 space-y-3">
            @forelse ($jugador->historialEstados as $cambio)
                <li class="rounded-lg border border-gray-700 p-3 text-sm text-gray-200">
                    <p class="font-semibold text-white">{{ $cambio->estado_anterior ?? 'Sin estado previo' }} → {{ $cambio->estado_nuevo }}</p>
                    <p class="mt-1">Motivo: {{ $cambio->motivo }}</p>
                    <p class="mt-1 text-gray-400">{{ $cambio->usuario->name }} · {{ $cambio->created_at?->timezone(\App\Services\GestionJugadorService::ZONA_HORARIA)->format('d/m/Y H:i') }}</p>
                </li>
            @empty
                <li class="text-sm text-gray-300">Aún no hay cambios de estado registrados.</li>
            @endforelse
        </ul>
    </section>
</main>
@endsection
