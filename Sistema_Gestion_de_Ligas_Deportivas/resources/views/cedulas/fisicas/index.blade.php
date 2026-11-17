@extends('layouts.admin')

@section('title', 'Cédulas en papel - SGLD')
@section('header', 'Cédulas en papel')

@section('content')
<div class="max-w-3xl">
    <p class="text-sm text-gray-300 mb-4">Partidos de hoy o anteriores cuya cédula todavía no se cierra. Elige el partido de la cédula que llegó en papel para transcribirla.</p>

    @if ($partidos->isEmpty())
        <x-alert type="info" message="No hay partidos con la cédula pendiente." />
    @else
        <ul class="space-y-3">
            @foreach ($partidos as $partido)
                <li class="bg-gray-800 border border-gray-700 rounded-xl p-4">
                    <p class="font-semibold text-white">{{ $partido->local?->nombre }} vs. {{ $partido->visitante?->nombre }}</p>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ $partido->fecha_partido?->format('d/m/Y') }}
                        {{ $partido->hora_partido ? '· '.substr((string) $partido->hora_partido, 0, 5) : '' }}
                        · {{ $partido->cancha?->nombre ?? 'Sin cancha' }}
                        @if ($partido->jornada?->grupo?->categoria)
                            · {{ $partido->jornada->grupo->categoria->nombre }}, jornada {{ $partido->jornada->numero_jornada }}
                        @endif
                        · Cédula {{ $partido->cedula ? ($partido->cedula->origen === 'papel' ? 'capturada desde papel, abierta' : 'abierta') : 'sin abrir' }}
                    </p>
                    <a href="{{ route('cedulas.fisica.create', $partido) }}" class="mt-3 inline-flex items-center justify-center w-full sm:w-auto min-h-12 px-5 py-3 text-base font-semibold text-white bg-green-600 hover:bg-green-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">
                        Capturar cédula en papel
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
