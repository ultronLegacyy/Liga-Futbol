@extends('layouts.portal')

@section('title', 'Boletín de castigados y comunicados')

@section('descripcion', 'Boletín oficial de castigados de la Liga Municipal de Fútbol: quién no puede jugar y hasta cuándo, y los comunicados oficiales.')

@section('estilos')
    .periodo { margin: 0; font-size: 1.0625rem; font-weight: 600; color: var(--blanco) !important; }
    .resumen { margin: .25rem 0 0; }

    .equipo-titulo { color: var(--texto); }
    .equipo-titulo span { display: inline-block; padding-bottom: .375rem; border-bottom: 4px solid var(--acento); }

    .castigados {
        display: grid;
        gap: 1rem;
        margin: 0 0 1.5rem;
        padding: 0;
        list-style: none;
    }

    .castigo {
        display: grid;
        grid-template-columns: 5.5rem minmax(0, 1fr);
        overflow-wrap: anywhere;
    }

    .castigo p { margin: 0; }

    .cuenta-castigo {
        display: grid;
        place-content: center;
        gap: .25rem;
        padding: 1rem .5rem;
        background: #c4121b;
        color: #fff;
        text-align: center;
    }

    .cuenta-castigo.temporal { background: var(--navy); }

    .cuenta-castigo b { font-family: var(--display); font-weight: 800; font-size: 2.75rem; line-height: .9; }
    .cuenta-castigo small { font-size: .75rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; line-height: 1.2; }

    .castigo .cuerpo { padding: 1rem 1.125rem; }
    .castigo .nombre { font-size: 1.125rem; font-weight: 800; line-height: 1.3; }
    .castigo .detalle { margin-top: .25rem; color: var(--texto-secundario); font-size: .9375rem; }

    .castigo dl { display: grid; grid-template-columns: auto 1fr; gap: .25rem .875rem; margin: .75rem 0 0; font-size: .9375rem; }
    .castigo dt { color: var(--texto-secundario); }
    .castigo dd { margin: 0; }

    .comunicados { display: grid; gap: 1rem; margin: 0; padding: 0; list-style: none; }
    .comunicado { padding: 1.25rem 1.5rem; border-left: 6px solid var(--lima); }
    .comunicado h3 { margin: 0; font-size: 1.1875rem; line-height: 1.3; }
    .comunicado time { display: block; margin-bottom: .25rem; color: var(--texto-secundario); font-size: .8125rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .comunicado p { margin: .5rem 0 0; white-space: pre-line; }

    .nota { margin: 1.5rem 0 0; color: var(--texto-secundario); font-size: .9375rem; }

    @media (min-width: 40rem) {
        .castigados { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
@endsection

@section('hero')
    <p class="etiqueta-hero"><span class="punto" aria-hidden="true"></span>Boletín oficial</p>
    <h1 class="display">Boletín de castigados</h1>
    <span class="linea" aria-hidden="true"></span>
    <p class="periodo">Vigente al {{ $hoy->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>
    <p class="resumen">{{ $total === 1 ? '1 persona suspendida' : $total.' personas suspendidas' }} · solo sanciones validadas por la Comisión Disciplinaria.</p>
@endsection

@section('content')
    @forelse ($porEquipo as $equipo => $sanciones)
        <section aria-labelledby="equipo-{{ $loop->index }}">
            <h2 class="display seccion-titulo equipo-titulo" id="equipo-{{ $loop->index }}"><span>{{ $equipo }}</span></h2>
            <ul class="castigados">
                @foreach ($sanciones as $sancion)
                    @php
                        $nombre = $sancion->persona
                            ? trim($sancion->persona->nombre.' '.$sancion->persona->apellidos)
                            : ($sancion->equipo?->nombre ?? 'Sancionado');
                        $porPartidos = $sancion->tipo_sancion === \App\Models\Sancion::TIPO_PARTIDOS;
                        $restantes = $sancion->partidosRestantes();
                    @endphp
                    <li class="castigo tarjeta">
                        <div class="cuenta-castigo {{ $porPartidos ? '' : 'temporal' }}">
                            @if ($porPartidos)
                                <b>{{ $restantes }}</b>
                                <small>{{ $restantes === 1 ? 'partido pendiente' : 'partidos pendientes' }}</small>
                            @else
                                <small>Suspendido hasta</small>
                                <b>{{ $sancion->fecha_fin?->format('d/m') ?? '—' }}</b>
                                @if ($sancion->fecha_fin)
                                    <small>{{ $sancion->fecha_fin->format('Y') }}</small>
                                @endif
                            @endif
                        </div>
                        <div class="cuerpo">
                            <p class="nombre">{{ $nombre }}</p>
                            <p class="detalle">
                                @if ($porPartidos)
                                    Suspensión de {{ $sancion->partidos_suspendidos }} {{ $sancion->partidos_suspendidos === 1 ? 'partido' : 'partidos' }}, {{ $sancion->partidos_cumplidos }} cumplido{{ $sancion->partidos_cumplidos === 1 ? '' : 's' }}
                                @else
                                    Suspensión temporal{{ $sancion->fecha_fin ? ' hasta el '.$sancion->fecha_fin->format('d/m/Y') : '' }}
                                @endif
                            </p>
                            <dl>
                                @if ($sancion->articulo)
                                    <dt>Motivo</dt>
                                    <dd>{{ $sancion->articulo->codigo }} · {{ $sancion->articulo->descripcion }}</dd>
                                @endif
                                @if ($sancion->fecha_inicio)
                                    <dt>Desde</dt>
                                    <dd>{{ $sancion->fecha_inicio->format('d/m/Y') }}</dd>
                                @endif
                                @if ($sancion->autorizado_por_cargo)
                                    <dt>Autoriza</dt>
                                    <dd>{{ $sancion->autorizado_por_cargo }}</dd>
                                @endif
                            </dl>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="vacio" role="status">
            <p>No hay castigos vigentes publicados en el boletín.</p>
            <p>Todos los jugadores pueden participar, salvo lo que indique la Comisión Disciplinaria.</p>
        </div>
    @endforelse

    <section aria-labelledby="comunicados-titulo">
        <h2 class="display seccion-titulo equipo-titulo" id="comunicados-titulo"><span>Comunicados oficiales</span></h2>
        @if ($comunicados->isEmpty())
            <div class="vacio" role="status"><p>No hay comunicados publicados.</p></div>
        @else
            <ul class="comunicados">
                @foreach ($comunicados as $comunicado)
                    <li class="comunicado tarjeta">
                        <article>
                            <time datetime="{{ $comunicado->publicado_en?->toDateString() }}">{{ $comunicado->publicado_en?->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</time>
                            <h3>{{ $comunicado->titulo }}</h3>
                            <p>{{ $comunicado->contenido }}</p>
                        </article>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <p class="nota">El boletín lo publica la Comisión Disciplinaria. Una sanción se retira sola cuando se cumple. Para aclaraciones, acude a la Secretaría de la liga.</p>
@endsection
