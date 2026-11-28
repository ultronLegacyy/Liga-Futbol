@extends('layouts.admin')

@section('title', 'Mis partidos - SGLD')
@section('header', 'Mis partidos')

{{--
    HAB-S7 · S7-T04 (SCRUM-237): inicio del Árbitro en Campo pensado para el celular en la cancha.
    Partidos agrupados en Hoy, Próximos y Anteriores (hora de México), hora grande, estado de la cédula
    con texto y un botón de 56 px a todo lo ancho para capturar o consultar.
--}}
@section('content')
@php
    $hoy = \Illuminate\Support\Carbon::today('America/Mexico_City')->toDateString();
    $grupos = [
        'Hoy' => $partidos->filter(fn ($p) => $p->fecha_partido?->toDateString() === $hoy),
        'Próximos' => $partidos->filter(fn ($p) => $p->fecha_partido === null || $p->fecha_partido->toDateString() > $hoy),
        'Anteriores' => $partidos->filter(fn ($p) => $p->fecha_partido !== null && $p->fecha_partido->toDateString() < $hoy)->reverse(),
    ];
    $estadoCedula = [
        'abierta' => ['Cédula abierta', 'bg-amber-400 text-gray-950'],
        'cerrada' => ['Cédula cerrada', 'bg-emerald-500 text-gray-950'],
    ];
    $iniciales = function (?string $nombre): string {
        $palabras = preg_split('/\s+/u', trim((string) $nombre), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(mb_substr($palabras[0], 0, 1).(isset($palabras[1]) ? mb_substr($palabras[1], 0, 1) : mb_substr($palabras[0], 1, 1)));
    };
@endphp

<div class="mx-auto max-w-3xl">
    @if ($partidos->isEmpty())
        <x-alert type="info" message="No tienes partidos asignados. Si eres árbitro, pide a la Comisión Arbitral que enlace tu cuenta con tu registro de árbitro." />
    @else
        <section class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-marino-800 via-electrico to-marino-900 p-5 text-white sm:p-7" aria-label="Resumen">
            <p class="text-xs font-bold uppercase tracking-[.16em] text-celeste">Árbitro en campo</p>
            <p class="display mt-2 text-4xl sm:text-5xl">{{ $grupos['Hoy']->count() === 1 ? '1 partido hoy' : $grupos['Hoy']->count().' partidos hoy' }}</p>
            <p class="mt-2 text-sm text-white/90">{{ $grupos['Próximos']->count() }} próximos · {{ $partidos->filter(fn ($p) => $p->cedula?->estado === 'abierta')->count() }} cédulas abiertas</p>
        </section>

        @foreach ($grupos as $titulo => $lista)
            @continue($lista->isEmpty())
            <section class="mb-8" aria-labelledby="grupo-{{ $loop->index }}">
                <h2 id="grupo-{{ $loop->index }}" class="display mb-3 text-3xl text-white"><span class="border-b-4 border-electrico pb-1">{{ $titulo }}</span></h2>
                <ul class="space-y-4">
                    @foreach ($lista as $partido)
                        @php
                            $cedula = $partido->cedula;
                            [$textoCedula, $claseCedula] = $estadoCedula[$cedula?->estado] ?? ['Cédula sin abrir', 'bg-gray-600 text-white'];
                            $capturar = $cedula?->estado === 'abierta';
                        @endphp
                        <li class="overflow-hidden rounded-2xl border border-gray-700 bg-gray-800 shadow-lg">
                            <div class="grid grid-cols-[3.5rem_minmax(0,1fr)_3.5rem] items-center gap-3 bg-gray-750 px-4 py-4 text-center">
                                <span class="grid h-14 w-14 place-items-center rounded-xl bg-electrico/25 font-black italic text-celeste" aria-hidden="true">{{ $iniciales($partido->local?->nombre) }}</span>
                                <div>
                                    <p class="display text-4xl text-white">{{ $partido->hora_partido ? substr((string) $partido->hora_partido, 0, 5) : '--:--' }}</p>
                                    <p class="mt-1 text-xs text-gray-300">{{ $partido->fecha_partido?->locale('es')->translatedFormat('D j \d\e M') ?? 'Sin fecha' }}</p>
                                </div>
                                <span class="grid h-14 w-14 place-items-center rounded-xl bg-electrico/25 font-black italic text-celeste" aria-hidden="true">{{ $iniciales($partido->visitante?->nombre) }}</span>
                            </div>
                            <div class="p-4">
                                <p class="text-lg font-bold leading-snug text-white">{{ $partido->local?->nombre ?? 'Por definir' }} <span class="font-normal text-gray-300">vs.</span> {{ $partido->visitante?->nombre ?? 'Por definir' }}</p>
                                <p class="mt-1 text-sm text-gray-300">{{ $partido->cancha?->nombre ?? 'Sin cancha' }} · Partido {{ $partido->estado }}</p>
                                <p class="mt-3"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $claseCedula }}">{{ $textoCedula }}</span></p>
                                <a href="{{ route('cedulas.show', $partido) }}" class="mt-4 flex min-h-14 w-full items-center justify-center rounded-xl px-5 text-base font-bold {{ $capturar ? 'bg-electrico text-white shadow-lg shadow-electrico/30 hover:bg-electrico-claro' : 'border border-gray-600 text-gray-100 hover:bg-gray-700' }}">
                                    {{ $capturar ? 'Capturar cédula' : 'Ver cédula' }}
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</div>
@endsection
