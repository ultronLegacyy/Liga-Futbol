@extends('layouts.admin')

@section('title', 'Mis partidos - SGLD')
@section('header', 'Mis partidos')

@section('content')
<div class="max-w-3xl">
    @if ($partidos->isEmpty())
        <x-alert type="info" message="No tienes partidos asignados. Si eres árbitro, pide a la Comisión Arbitral que enlace tu cuenta con tu registro de árbitro." />
    @else
        <ul class="space-y-3">
            @foreach ($partidos as $partido)
                <li class="bg-gray-800 border border-gray-700 rounded-xl p-4">
                    <p class="font-semibold text-white">{{ $partido->local?->nombre }} vs. {{ $partido->visitante?->nombre }}</p>
                    <p class="text-sm text-gray-400 mt-1">
                        {{ $partido->fecha_partido?->format('d/m/Y') ?? 'Sin fecha' }}
                        {{ $partido->hora_partido ? '· '.substr((string) $partido->hora_partido, 0, 5) : '' }}
                        · {{ $partido->cancha?->nombre ?? 'Sin cancha' }}
                        · Partido {{ $partido->estado }}
                        · Cédula {{ $partido->cedula ? $partido->cedula->estado : 'sin abrir' }}
                    </p>
                    <a href="{{ route('cedulas.show', $partido) }}" class="mt-3 inline-flex items-center justify-center w-full sm:w-auto min-h-12 px-5 py-3 text-base font-semibold text-white bg-green-600 hover:bg-green-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">
                        {{ $partido->cedula && $partido->cedula->estado === 'abierta' ? 'Capturar cédula' : 'Ver cédula' }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
