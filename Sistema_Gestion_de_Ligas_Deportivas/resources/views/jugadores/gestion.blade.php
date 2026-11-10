@extends('layouts.admin')

@section('title', 'Ficha del jugador - SGLD')
@section('header', 'Ficha del jugador')

@section('content')
<main class="mx-auto max-w-5xl space-y-6">
    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

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
        @can('deactivate', $jugador)
            @if ($jugador->estado_elegibilidad === 'Activo')
                <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7">
                    <h2 class="text-lg font-semibold text-white">Dar de baja</h2>
                    <form method="POST" action="{{ route('jugadores.baja', $jugador) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <textarea name="motivo" required minlength="3" maxlength="1000" aria-label="Motivo de baja" class="rounded-lg border border-gray-600 bg-gray-900 p-3 text-white"></textarea>
                        <div><input name="fecha_baja" type="date" max="{{ now()->toDateString() }}" required aria-label="Fecha de baja" class="rounded-lg border border-gray-600 bg-gray-900 p-3 text-white"><button class="ml-3 rounded-lg bg-red-700 px-4 py-2 font-semibold text-white">Confirmar baja</button></div>
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
        <section class="rounded-xl border border-gray-700 bg-gray-800 p-5 sm:p-7"><h2 class="text-lg font-semibold text-white">Historial de transferencias</h2><ul class="mt-3 space-y-2">@foreach ($jugador->transferenciasOrigen as $transferencia)<li class="text-sm text-gray-200">{{ $transferencia->created_at?->format('d/m/Y H:i') }} · destino {{ $transferencia->clubDestino->nombre }} / {{ $transferencia->equipoDestino->nombre }}</li>@endforeach</ul></section>
    @endif
</main>
@endsection
