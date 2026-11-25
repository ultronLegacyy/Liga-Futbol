@extends('layouts.admin')

@section('title', 'Mi club - SGLD')
@section('header', 'Mi club')

{{-- HAB-S7 · S7-T05 (SCRUM-238): resumen del club o del equipo propio, primero para el celular (RNF-10, RNF-29) --}}
@section('content')
<div class="mx-auto max-w-4xl">
    @if (! $club)
        <div class="rounded-2xl border border-dashed border-gray-600 bg-gray-800 p-6" role="status">
            <p class="font-semibold text-white">Tu cuenta todavía no está ligada a un club.</p>
            @if ($esDirectivo)
                <p class="mt-1 text-sm text-gray-300">Pide a la Secretaría de la liga que asigne tu club a tu cuenta.</p>
            @else
                <p class="mt-1 text-sm text-gray-300">Vincula tu cuenta con tu registro de jugador para ver tu equipo.</p>
                @can('link', App\Models\Jugador::class)
                    <a href="{{ route('jugadores.vincular.form') }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-electrico px-4 font-bold text-white">Vincular mi cuenta</a>
                @endcan
            @endif
        </div>
    @else
        <section class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-marino-800 via-electrico to-marino-900 p-6 text-white sm:p-8" aria-labelledby="club-nombre">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-celeste">{{ $esDirectivo ? 'Tu club' : 'Tu equipo' }}</p>
            <h2 id="club-nombre" class="display mt-2 text-5xl">{{ $esDirectivo ? $club->nombre : ($equipos->first()->nombre ?? $club->nombre) }}</h2>
            <dl class="mt-5 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-2xl bg-white/15 p-3"><dt class="text-xs font-bold uppercase tracking-wider text-white/80">Equipos</dt><dd class="display mt-1 text-3xl">{{ $equipos->count() }}</dd></div>
                <div class="rounded-2xl bg-white/15 p-3"><dt class="text-xs font-bold uppercase tracking-wider text-white/80">Jugadores</dt><dd class="display mt-1 text-3xl">{{ $equipos->sum('activos') }}</dd></div>
                <div class="rounded-2xl bg-white/15 p-3"><dt class="text-xs font-bold uppercase tracking-wider text-white/80">Partidos</dt><dd class="display mt-1 text-3xl">{{ $partidos->count() }}</dd></div>
            </dl>
        </section>

        <section class="mb-8" aria-labelledby="titulo-partidos">
            <h2 id="titulo-partidos" class="display mb-3 text-3xl text-white"><span class="border-b-4 border-electrico pb-1">Próximos partidos</span></h2>
            @if ($partidos->isEmpty())
                <p class="rounded-2xl border border-dashed border-gray-600 bg-gray-800 p-5 text-gray-300">No hay partidos programados en los próximos 14 días.</p>
            @else
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ($partidos as $partido)
                        <li class="rounded-2xl border border-gray-700 bg-gray-800 p-4">
                            <p class="display text-3xl text-celeste">{{ $partido->hora_partido ? substr((string) $partido->hora_partido, 0, 5) : '--:--' }}</p>
                            <p class="mt-1 text-sm text-gray-300">{{ $partido->fecha_partido?->locale('es')->translatedFormat('l j \d\e F') }}</p>
                            <p class="mt-2 font-bold text-white">{{ $partido->local?->nombre }} <span class="font-normal text-gray-300">vs.</span> {{ $partido->visitante?->nombre }}</p>
                            <p class="mt-1 text-sm text-gray-300">{{ $partido->cancha?->nombre ?? 'Cancha por confirmar' }} · {{ ucfirst($partido->estado) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mb-8" aria-labelledby="titulo-equipos">
            <h2 id="titulo-equipos" class="display mb-3 text-3xl text-white"><span class="border-b-4 border-electrico pb-1">{{ $esDirectivo ? 'Equipos' : 'Equipo' }}</span></h2>
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($equipos as $equipo)
                    <li class="rounded-2xl border border-gray-700 bg-gray-800 p-4">
                        <p class="font-bold text-white">{{ $equipo->nombre }}</p>
                        <p class="mt-1 text-sm text-gray-300">{{ $equipo->grupo?->categoria?->nombre ?? 'Sin categoría' }}{{ $equipo->grupo ? ' · '.$equipo->grupo->nombre : '' }}</p>
                        <p class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
                            <span class="rounded-full bg-emerald-500 px-3 py-1 text-gray-950">{{ $equipo->activos }} activos</span>
                            @if ($equipo->suspendidos > 0)
                                <span class="rounded-full bg-red-600 px-3 py-1 text-white">{{ $equipo->suspendidos }} suspendidos</span>
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="mb-8" aria-labelledby="titulo-castigos">
            <h2 id="titulo-castigos" class="display mb-3 text-3xl text-white"><span class="border-b-4 border-electrico pb-1">Castigos vigentes</span></h2>
            @if ($castigados->isEmpty())
                <p class="rounded-2xl border border-dashed border-gray-600 bg-gray-800 p-5 text-gray-300">Nadie del {{ $esDirectivo ? 'club' : 'equipo' }} tiene castigos vigentes publicados.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($castigados as $sancion)
                        <li class="flex items-center gap-4 rounded-2xl border border-red-500/40 bg-gray-800 p-4">
                            <span class="display grid h-14 w-14 flex-none place-items-center rounded-xl bg-red-600 text-2xl text-white">{{ $sancion->tipo_sancion === App\Models\Sancion::TIPO_PARTIDOS ? $sancion->partidosRestantes() : '—' }}</span>
                            <span>
                                <span class="block font-bold text-white">{{ $sancion->persona ? trim($sancion->persona->nombre.' '.$sancion->persona->apellidos) : ($sancion->equipo?->nombre ?? 'Sancionado') }}</span>
                                <span class="block text-sm text-gray-300">
                                    @if ($sancion->tipo_sancion === App\Models\Sancion::TIPO_PARTIDOS)
                                        {{ $sancion->partidosRestantes() === 1 ? 'partido pendiente' : 'partidos pendientes' }}
                                    @else
                                        Suspendido hasta el {{ $sancion->fecha_fin?->format('d/m/Y') }}
                                    @endif
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($esDirectivo)
            <section aria-labelledby="titulo-acciones">
                <h2 id="titulo-acciones" class="display mb-3 text-3xl text-white"><span class="border-b-4 border-electrico pb-1">Acciones</span></h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @can('jugadores.crear')
                        <a href="{{ route('jugadores.create') }}" class="flex min-h-14 items-center justify-center rounded-xl bg-electrico px-4 font-bold text-white hover:bg-electrico-claro">Registrar jugadores</a>
                    @endcan
                    @can('viewAny', App\Models\Jugador::class)
                        <a href="{{ route('jugadores.index') }}" class="flex min-h-14 items-center justify-center rounded-xl border border-gray-600 px-4 font-bold text-gray-100 hover:bg-gray-700">Ver jugadores</a>
                    @endcan
                </div>
            </section>
        @else
            <p class="rounded-2xl border border-gray-700 bg-gray-800 p-4 text-sm text-gray-300">«Mi perfil» con tu credencial, tus partidos y tus sanciones llegará cuando la liga lo apruebe.</p>
        @endif
    @endif
</div>
@endsection
