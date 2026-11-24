@extends('layouts.portal')

@section('title', 'Boletín de castigados')

@section('descripcion', 'Boletín oficial de castigados de la Liga Municipal de Fútbol: quién no puede jugar y hasta cuándo.')

@section('estilos')
    .periodo { margin: 1rem 0 0; font-size: 1.0625rem; font-weight: 600; }
    .resumen { margin: .25rem 0 0; opacity: .85; }

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
        background: linear-gradient(160deg, #d9251b, #8f0f08);
        color: #fff;
        text-align: center;
    }

    .cuenta-castigo.temporal { background: linear-gradient(160deg, #1f5eff, #082a86); }

    .cuenta-castigo b { font-family: var(--display); font-weight: 900; font-style: italic; font-size: 2.75rem; line-height: .9; }
    .cuenta-castigo small { font-size: .75rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; line-height: 1.2; }

    .castigo .cuerpo { padding: 1rem 1.125rem; }
    .castigo .nombre { font-size: 1.125rem; font-weight: 800; line-height: 1.3; }
    .castigo .detalle { margin-top: .25rem; color: var(--texto-secundario); font-size: .9375rem; }

    .castigo dl { display: grid; grid-template-columns: auto 1fr; gap: .25rem .875rem; margin: .75rem 0 0; font-size: .9375rem; }
    .castigo dt { color: var(--texto-secundario); }
    .castigo dd { margin: 0; }

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

    <p class="nota">El boletín lo publica la Comisión Disciplinaria. Una sanción se retira sola cuando se cumple. Para aclaraciones, acude a la Secretaría de la liga.</p>
@endsection
