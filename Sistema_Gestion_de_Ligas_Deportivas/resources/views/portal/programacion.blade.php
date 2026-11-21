@extends('layouts.portal')

@section('title', 'Programación de partidos')

@section('descripcion', 'Programación semanal de partidos de la Liga Municipal de Fútbol: fecha, hora, cancha y equipos.')

@section('estilos')
    h1 { font-size: 1.5rem; line-height: 1.25; margin: .5rem 0 .25rem; }
    h2 { font-size: 1.125rem; margin: 0 0 .75rem; }
    .periodo { color: var(--texto-secundario); margin: 0 0 1rem; }

    .semanas {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin: 0 0 1.5rem;
        padding: 0;
        list-style: none;
    }

    .semanas a {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 0 1rem;
        border: 1px solid var(--borde);
        border-radius: .5rem;
        background: var(--tarjeta);
        text-decoration: none;
    }

    .dia { margin: 0 0 1.5rem; }

    .partidos {
        display: grid;
        gap: .75rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .partido {
        padding: 1rem;
        border: 1px solid var(--borde);
        border-radius: .75rem;
        background: var(--tarjeta);
        overflow-wrap: anywhere;
    }

    .partido p { margin: 0; }
    .partido .hora { font-weight: 700; font-size: 1.125rem; }
    .partido .equipos { margin: .25rem 0 .5rem; font-size: 1.0625rem; font-weight: 600; }
    .partido .vs { color: var(--texto-secundario); font-weight: 400; }

    .partido dl { display: grid; grid-template-columns: auto 1fr; gap: .125rem .75rem; margin: 0; font-size: .9375rem; }
    .partido dt { color: var(--texto-secundario); }
    .partido dd { margin: 0; }

    .estado {
        display: inline-block;
        margin-left: .5rem;
        padding: .125rem .5rem;
        border-radius: 999px;
        border: 1px solid currentColor;
        font-size: .8125rem;
        font-weight: 600;
        vertical-align: middle;
        color: #f5c86b;
    }

    .estado-cancelado, .estado-suspendido { color: #ffb4a8; }
    .estado-finalizado, .estado-en-juego { color: #8bd6a9; }

    .vacio {
        padding: 1.25rem;
        border: 1px dashed var(--borde);
        border-radius: .75rem;
    }

    @media (min-width: 48rem) {
        .partidos { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
@endsection

@section('content')
    @php
        $fecha = static fn ($dia, string $formato): string => \Illuminate\Support\Str::ucfirst($dia->locale('es')->translatedFormat($formato));
        $etiquetas = [
            'en juego' => 'En juego',
            'finalizado' => 'Finalizado',
            'suspendido' => 'Suspendido',
            'cancelado' => 'Cancelado',
            'reprogramado' => 'Reprogramado',
        ];
    @endphp

    <h1>Programación de partidos</h1>
    <p class="periodo">
        Semana del {{ $lunes->locale('es')->translatedFormat('j \d\e F') }} al {{ $domingo->locale('es')->translatedFormat('j \d\e F \d\e Y') }}
    </p>

    <nav aria-label="Cambiar de semana">
        <ul class="semanas">
            <li><a href="{{ route('portal.programacion', ['semana' => $lunes->subWeek()->toDateString()]) }}">← Semana anterior</a></li>
            @unless ($esSemanaActual)
                <li><a href="{{ route('portal.programacion') }}">Semana actual</a></li>
            @endunless
            <li><a href="{{ route('portal.programacion', ['semana' => $lunes->addWeek()->toDateString()]) }}">Semana siguiente →</a></li>
        </ul>
    </nav>

    @forelse ($dias as $dia => $partidos)
        @php($diaFecha = \Carbon\CarbonImmutable::parse($dia))
        <section class="dia" aria-labelledby="dia-{{ $dia }}">
            <h2 id="dia-{{ $dia }}"><time datetime="{{ $dia }}">{{ $fecha($diaFecha, 'l j \d\e F') }}</time></h2>
            <ol class="partidos">
                @foreach ($partidos as $partido)
                    @php($categoria = $partido->jornada?->grupo?->categoria)
                    <li class="partido">
                        <article aria-label="{{ $partido->local?->nombre }} contra {{ $partido->visitante?->nombre }}">
                            <p class="hora">
                                @if ($partido->hora_partido)
                                    <time datetime="{{ $dia }}T{{ substr($partido->hora_partido, 0, 5) }}">{{ substr($partido->hora_partido, 0, 5) }} h</time>
                                @else
                                    Hora por confirmar
                                @endif
                                @isset($etiquetas[$partido->estado])
                                    <span class="estado estado-{{ str_replace(' ', '-', $partido->estado) }}">{{ $etiquetas[$partido->estado] }}</span>
                                @endisset
                            </p>
                            <p class="equipos">
                                {{ $partido->local?->nombre ?? 'Por definir' }}
                                <span class="vs">vs.</span>
                                {{ $partido->visitante?->nombre ?? 'Por definir' }}
                            </p>
                            <dl>
                                <dt>Cancha</dt>
                                <dd>{{ $partido->cancha?->nombre ?? 'Por confirmar' }}@if ($partido->cancha?->direccion_ubicacion) · {{ $partido->cancha->direccion_ubicacion }}@endif</dd>
                                @if ($categoria)
                                    <dt>Categoría</dt>
                                    <dd>{{ $categoria->nombre }} ({{ $categoria->rama }})@if ($partido->jornada?->grupo?->nombre) · {{ $partido->jornada->grupo->nombre }}@endif</dd>
                                @endif
                                <dt>Jornada</dt>
                                <dd>{{ $partido->jornada?->numero_jornada }}</dd>
                            </dl>
                        </article>
                    </li>
                @endforeach
            </ol>
        </section>
    @empty
        <div class="vacio" role="status">
            <p>No hay partidos publicados para esta semana.</p>
            @if ($siguienteConPartidos)
                <p><a href="{{ route('portal.programacion', ['semana' => $siguienteConPartidos->toDateString()]) }}">Ver la semana del {{ $siguienteConPartidos->locale('es')->translatedFormat('j \d\e F') }}, que ya tiene partidos</a></p>
            @endif
        </div>
    @endforelse
@endsection
