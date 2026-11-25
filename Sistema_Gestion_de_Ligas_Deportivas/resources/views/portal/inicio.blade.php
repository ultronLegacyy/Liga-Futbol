@extends('layouts.portal')

@section('title', 'Inicio')

@section('descripcion', 'Programación, resultados, tablas y boletín de la Liga Municipal de Fútbol Amateur de Morelia.')

{{--
    Portada del portal: el prototipo «SGLD · Portal deportivo» aprobado por el Product Owner (25/11/2026),
    con datos reales y sin JavaScript (RNF-04). El filtro de categorías y las pestañas de tablas son radios con
    :has(); si el navegador no lo soporta, se ve todo sin filtrar.
--}}
@php
    $partidosSemana = $partidos;
    $enJuego = $partidosSemana->firstWhere('estado', 'en juego');
    $proximo = $partidosSemana->first(fn ($p) => ! in_array($p->estado, ['finalizado', 'cancelado', 'en juego'], true));
    $jornadaNumero = $partidosSemana->pluck('jornada.numero_jornada')->filter()->min();
    $primeraTabla = $grupos->isNotEmpty() ? ($tablas[$grupos->first()->id] ?? collect()) : collect();
    $etiquetaEstado = ['en juego' => 'En juego', 'finalizado' => 'Final', 'cancelado' => 'Cancelado', 'suspendido' => 'Suspendido', 'reprogramado' => 'Reprogramado'];
@endphp

@section('estilos')
    .btn { display: inline-flex; align-items: center; gap: .625rem; min-height: 52px; padding: 0 1.75rem; border-radius: 999px; font-weight: 800; font-size: .875rem; letter-spacing: .08em; text-transform: uppercase; text-decoration: none; transition: translate .2s, background-color .2s, color .2s; }
    .btn:hover { translate: 0 -3px; }
    .btn-o { border: 2px solid currentColor; }
    .btn-o:hover { background: var(--blanco); color: var(--navy) !important; }
    .btn-a { background: var(--lima); color: var(--tinta) !important; }
    .ctas { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.75rem; }

    .sec { margin-top: 4rem; }
    .eyebrow { display: flex; align-items: center; gap: .625rem; margin: 0; color: var(--texto-secundario); font-size: .75rem; font-weight: 800; letter-spacing: .22em; text-transform: uppercase; }
    .eyebrow::before { content: ""; width: .5rem; height: .5rem; border-radius: 50%; background: var(--royal); }
    .h2 { margin: .75rem 0 0; font-size: clamp(2.5rem, 9vw, 4rem); }
    .h2 em { color: var(--royal); font-style: inherit; }

    /* Categorías: radios con :has() que filtran el carrusel */
    .cats { display: flex; gap: 1rem; margin: 2rem -1rem 0; padding: .5rem 1rem 1rem; overflow-x: auto; scrollbar-width: none; }
    .cats::-webkit-scrollbar { display: none; }
    .cats input { position: absolute; opacity: 0; pointer-events: none; }
    .cat { display: flex; flex: none; flex-direction: column; align-items: center; gap: .75rem; min-width: 5.5rem; cursor: pointer; font-size: .75rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; text-align: center; }
    .cat .c { position: relative; display: grid; place-items: center; width: 5.5rem; height: 5.5rem; border: 1.5px solid var(--borde); border-radius: 50%; background: var(--tarjeta); color: var(--royal); transition: translate .3s, rotate .3s, background-color .2s, color .2s, border-color .2s; }
    .cat .c svg { width: 2.25rem; height: 2.25rem; }
    .cat .c b { position: absolute; top: -.25rem; right: -.25rem; display: grid; place-items: center; min-width: 1.75rem; height: 1.75rem; padding: 0 .375rem; border-radius: 999px; background: var(--tinta); color: var(--blanco); font-size: .75rem; }
    .cat:hover .c { translate: 0 -6px; rotate: -6deg; border-color: var(--royal); }
    .cats input:checked + .cat .c { background: var(--royal); color: var(--blanco); border-color: var(--royal); box-shadow: 0 18px 34px -14px var(--royal); }
    .cats input:focus-visible + .cat .c { outline: 3px solid var(--foco); outline-offset: 3px; }

    .jornada { display: grid; gap: 1.5rem; align-items: center; margin-top: 2.5rem; }
    .jl .h2 { font-size: clamp(3.25rem, 12vw, 4.75rem); }
    .jl .raya { display: block; width: 2.75rem; height: 4px; margin: 1.25rem 0; background: var(--tinta); }
    .jl p { margin: 0; color: var(--texto-secundario); }
    .enlace { display: inline-flex; align-items: center; gap: .75rem; margin-top: 1.5rem; padding-bottom: .25rem; border-bottom: 2px solid currentColor; color: var(--tinta); font-size: .8125rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; text-decoration: none; }
    .pista { display: flex; gap: 1.125rem; margin: 0 -1rem; padding: .625rem 1rem 1.5rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; list-style: none; }
    .pista::-webkit-scrollbar { display: none; }
    .mc { flex: 0 0 15rem; scroll-snap-align: start; }
    .mc .arriba { position: relative; display: flex; align-items: center; justify-content: center; gap: .375rem; height: 11rem; background: var(--suave); }
    .mc .escudo-svg { transition: translate .4s, rotate .4s; }
    .mc:hover .escudo-svg:first-of-type { translate: -6px 0; rotate: -6deg; }
    .mc:hover .escudo-svg:last-of-type { translate: 6px 0; rotate: 6deg; }
    .mc .vs { font-size: 1.75rem; color: var(--texto-secundario); }
    .mc .gol { font-size: 2.5rem; color: var(--tinta); }
    .tag { position: absolute; top: .75rem; left: .75rem; padding: .375rem .625rem; border-radius: .5rem; background: var(--tarjeta); font-size: .625rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .tag.vivo { background: #d9251b; color: var(--blanco); }
    .tag.final { background: var(--tinta); color: var(--blanco); }
    .mc .abajo { padding: 1rem 1.125rem 1.125rem; }
    .mc .abajo p { margin: 0; }
    .mc .equipos { font-weight: 800; font-size: .9375rem; line-height: 1.3; }
    .mc .cuando { margin-top: .25rem !important; font-weight: 800; font-size: .875rem; }
    .mc .donde { color: var(--texto-secundario); font-size: .8125rem; }

    /* Lo que todos buscan */
    .cols { display: grid; gap: 1.25rem; margin-top: 2rem; }
    .col { position: relative; display: flex; flex-direction: column; min-height: 23rem; padding: 2rem; overflow: hidden; border-radius: 1.75rem; color: var(--blanco); text-decoration: none; isolation: isolate; }
    .col .fondo { position: absolute; inset: 0; z-index: -1; transition: scale .7s cubic-bezier(.2, .8, .2, 1); }
    .col:hover .fondo { scale: 1.07; }
    .col h3 { margin: 0; font-size: 2.75rem; }
    .col p { max-width: 14rem; margin: .75rem 0 0; font-size: .875rem; opacity: .9; }
    .col .enlace { margin-top: auto; color: inherit; width: max-content; }
    .c1 { color: var(--tinta); }
    .c1 .fondo { background: linear-gradient(160deg, #eef1f5, #d7dde6); }
    .c2 .fondo { background: linear-gradient(160deg, #3a3f47, #15171b); }
    .c3 .fondo { background: linear-gradient(160deg, #0f3594, #081b52); }
    .numero-grande { position: absolute; right: -.5rem; bottom: -2rem; font-size: 13rem; opacity: .09; }
    .pos { position: absolute; top: 7.5rem; right: 1.5rem; display: grid; gap: .625rem; width: 60%; }
    .pos div { display: flex; align-items: center; gap: .75rem; padding: .75rem .875rem; border-radius: 1rem; background: var(--tarjeta); box-shadow: 0 12px 24px -16px rgba(0, 0, 0, .4); font-size: .8125rem; font-weight: 700; transition: translate .4s; }
    .col:hover .pos div:first-child { translate: -10px 0; }
    .col:hover .pos div:nth-child(2) { translate: -4px 0; }
    .pos div b { width: 1.25rem; font-family: var(--display); font-style: italic; font-size: 1.375rem; }
    .pos div em { margin-left: auto; color: var(--royal); font-style: normal; font-weight: 800; }
    .pos .escudo-svg { flex: none; }
    .naipes { position: absolute; top: 5.5rem; right: 1.25rem; width: 14rem; height: 16rem; }
    .naipes i { position: absolute; width: 7.5rem; height: 10.5rem; border-radius: .875rem; box-shadow: 0 30px 40px -18px rgba(0, 0, 0, .6); transition: rotate .6s cubic-bezier(.2, .8, .2, 1), translate .6s; }
    .naipes .y { top: 2rem; left: 1.25rem; background: linear-gradient(150deg, #ffe14d, #f5b400); rotate: -14deg; }
    .naipes .r { top: .5rem; left: 6rem; background: linear-gradient(150deg, #ff5a4f, #c4121b); rotate: 12deg; }
    .col:hover .naipes .y { rotate: -22deg; translate: -10px -6px; }
    .col:hover .naipes .r { rotate: 20deg; translate: 10px -10px; }
    .contador { display: inline-block; margin-top: 1rem; padding: .375rem .75rem; border-radius: 999px; background: rgba(255, 255, 255, .14); font-size: .8125rem; font-weight: 700; }
    .playera { position: absolute; top: 4.5rem; right: -1.25rem; width: 15rem; rotate: 10deg; transition: rotate .7s, translate .7s; }
    .col:hover .playera { rotate: 3deg; translate: 0 -10px; }

    /* Beneficios */
    .ventajas { display: grid; margin-top: 3rem; border-top: 1px solid var(--borde); border-bottom: 1px solid var(--borde); }
    .ventaja { display: flex; align-items: center; gap: 1rem; padding: 1.25rem .375rem; }
    .ventaja + .ventaja { border-top: 1px solid var(--borde); }
    .ventaja svg { flex: none; width: 2.125rem; height: 2.125rem; color: var(--royal); }
    .ventaja b { display: block; font-size: .8125rem; letter-spacing: .1em; text-transform: uppercase; }
    .ventaja span { color: var(--texto-secundario); font-size: .8125rem; }

    /* Banner de tablas con pestañas (radios + :has) */
    .banner { position: relative; display: grid; gap: 2rem; margin-top: 4rem; padding: 2.25rem 1.5rem; overflow: hidden; border-radius: 1.875rem; background: linear-gradient(120deg, #081b52, #0f3594); color: var(--blanco); }
    .banner .estelas { inset: 58% 30% 2% -20%; opacity: .55; }
    .banner h2 { position: relative; margin: 0; font-size: clamp(3.25rem, 12vw, 5.25rem); }
    .banner h2 span { display: block; }
    .banner h2 span:nth-child(2) { color: var(--lima); }
    .banner p { position: relative; max-width: 22rem; margin: 1.25rem 0 1.75rem; opacity: .85; }
    .energia { position: relative; display: flex; flex-wrap: wrap; gap: 1.25rem 2rem; margin-top: 2rem; }
    .energia div { font-size: .75rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; line-height: 1.35; }
    .energia b { display: block; font-family: var(--display); font-style: italic; font-size: 2rem; color: var(--lima); letter-spacing: 0; }
    .tablero { position: relative; padding: 1rem; border: 1px solid rgba(255, 255, 255, .14); border-radius: 1.5rem; background: rgba(255, 255, 255, .07); }
    .pestanas { display: flex; gap: .5rem; width: max-content; max-width: 100%; margin-bottom: 1rem; padding: .3125rem; overflow-x: auto; border-radius: 999px; background: rgba(0, 0, 0, .25); }
    .pestanas input { position: absolute; opacity: 0; pointer-events: none; }
    .pestanas label { flex: none; padding: .5625rem 1rem; border-radius: 999px; cursor: pointer; font-size: .75rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; opacity: .8; }
    .pestanas input:checked + label { background: var(--lima); color: var(--tinta); opacity: 1; }
    .pestanas input:focus-visible + label { outline: 3px solid var(--foco); }
    .fila { display: grid; grid-template-columns: 1.75rem minmax(0, 1fr) 2.25rem 2.75rem 2.75rem; align-items: center; gap: .5rem; padding: .625rem .625rem; border-radius: .875rem; font-size: .875rem; }
    .fila.cabeza { font-size: .6875rem; font-weight: 800; letter-spacing: .12em; opacity: .6; }
    .fila.lider { background: rgba(255, 255, 255, .1); }
    .fila .p { font-family: var(--display); font-style: italic; font-weight: 900; font-size: 1.375rem; }
    .fila.lider .p { color: var(--lima); }
    .fila .eq { display: flex; align-items: center; gap: .625rem; font-weight: 700; min-width: 0; }
    .fila .eq span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fila .pts { font-family: var(--display); font-weight: 900; font-size: 1.5rem; text-align: right; }
    .fila .num { text-align: right; }
    .panel-tabla + .panel-tabla { margin-top: 1.25rem; }
    .panel-tabla h3 { margin: 0 0 .5rem; font-size: .8125rem; letter-spacing: .1em; text-transform: uppercase; opacity: .75; }

    /* Goleadores */
    .goleo { display: grid; gap: 1.25rem; align-items: center; margin-top: 4rem; padding: 1.5rem; border: 1px solid var(--borde); border-radius: 1.75rem; background: var(--tarjeta); }
    .goleo .h2 { font-size: clamp(2.25rem, 8vw, 2.75rem); }
    .jugadores { display: flex; gap: .875rem; margin: 0; padding: 0; overflow-x: auto; scrollbar-width: none; list-style: none; }
    .jugadores::-webkit-scrollbar { display: none; }
    .pl { position: relative; display: flex; flex: 0 0 9.5rem; flex-direction: column; justify-content: flex-end; height: 11.5rem; padding: .875rem; overflow: hidden; border-radius: 1.125rem; color: var(--blanco); transition: translate .3s; }
    .pl:hover { translate: 0 -6px; }
    .pl svg { position: absolute; top: -.375rem; right: -1.625rem; width: 9.5rem; transition: rotate .5s, scale .5s; }
    .pl:hover svg { rotate: -8deg; scale: 1.05; }
    .pl::after { content: ""; position: absolute; inset: 0; background: linear-gradient(0deg, rgba(0, 0, 0, .78), transparent 60%); }
    .pl > * { position: relative; z-index: 1; }
    .pl b { font-size: .8125rem; }
    .pl small { font-size: .6875rem; opacity: .85; }
    .pl .g { position: absolute; top: .75rem; left: .875rem; z-index: 2; font-family: var(--display); font-style: italic; font-weight: 900; font-size: 2.5rem; line-height: 1; }
    .pl .g small { margin-left: .2rem; font-size: .8125rem; }

    /* Invitación final */
    .invita { display: flex; flex-direction: column; align-items: flex-start; gap: 1rem; margin-top: 4rem; padding: 1.75rem 1.5rem; border: 1px solid var(--borde); border-radius: 1.5rem; background: var(--tarjeta); }
    .invita h2 { margin: 0; font-size: 2.125rem; }
    .invita p { margin: .375rem 0 0; color: var(--texto-secundario); }
    .invita .btn-a { background: var(--royal); color: var(--blanco) !important; }

    @media (min-width: 48rem) {
        .cats { justify-content: space-between; margin: 2.75rem 0 0; padding: .5rem 0 1rem; }
        .cat .c { width: 6.75rem; height: 6.75rem; }
        .jornada { grid-template-columns: 16rem minmax(0, 1fr); gap: 2.5rem; margin-top: 4rem; }
        .pista { margin: 0; padding-left: .25rem; padding-right: .25rem; }
        .mc { flex-basis: 16.25rem; }
        .cols { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .ventajas { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .ventaja { padding: 1.75rem 1.5rem; }
        .ventaja + .ventaja { border-top: 0; border-left: 1px solid var(--borde); }
        .banner { grid-template-columns: 1fr 1.1fr; padding: 3.75rem; }
        .tablero { padding: 1.375rem; }
        .fila { grid-template-columns: 2rem minmax(0, 1fr) 2.5rem 3rem 3rem; }
        .goleo { grid-template-columns: 17rem minmax(0, 1fr); }
        .invita { flex-direction: row; align-items: center; justify-content: space-between; padding: 2rem 2.25rem; }
    }

    {{-- Filtro por categoría y pestañas de tablas, solo si el navegador soporta :has() --}}
    @supports selector(:has(*)) {
        @foreach ($categorias as $categoria)
            .jornada:has(#cat-{{ $categoria->id }}:checked) .mc:not([data-c="{{ $categoria->id }}"]) { display: none; }
        @endforeach
        .tablero .panel-tabla { display: none; }
        @foreach ($grupos as $grupo)
            .tablero:has(#tab-{{ $grupo->id }}:checked) #panel-{{ $grupo->id }} { display: block; }
        @endforeach
        .panel-tabla h3 { display: none; }
    }
@endsection

@section('hero')
    <p class="etiqueta-hero"><span class="punto" aria-hidden="true"></span>Temporada en curso{{ $jornadaNumero ? ' · Jornada '.$jornadaNumero : '' }}</p>
    <h1 class="display">Sin límites.<br>Pura cancha.</h1>
    <span class="linea" aria-hidden="true"></span>
    <p>Programación, resultados, tablas y boletín de la Liga Municipal de Fútbol Amateur de Morelia. En vivo, desde cualquier pantalla.</p>
    <div class="ctas">
        <a class="btn btn-o" href="#jornada">Ver jornada</a>
        <a class="btn btn-a" href="{{ route('portal.resultados') }}">Tabla general <span class="flecha" aria-hidden="true">→</span></a>
    </div>
@endsection

@section('hero-tarjetas')
    @if ($enJuego)
        <div class="vidrio vidrio-1">
            <span class="punto" aria-hidden="true"></span>
            <p><b>En juego</b><span>{{ $enJuego->local?->nombre }} vs. {{ $enJuego->visitante?->nombre }}</span></p>
        </div>
    @endif
    @if ($proximo)
        <div class="vidrio {{ $enJuego ? 'vidrio-2' : 'vidrio-1' }}">
            <span class="icono" aria-hidden="true">{{ $proximo->hora_partido ? substr($proximo->hora_partido, 0, 2) : '—' }}</span>
            <p><b>Próximo: {{ $proximo->fecha_partido?->locale('es')->translatedFormat('D') }} {{ $proximo->hora_partido ? substr($proximo->hora_partido, 0, 5) : '' }}</b><span>{{ $proximo->cancha?->nombre ?? 'Cancha por confirmar' }}</span></p>
        </div>
    @endif
@endsection

@section('hero-cifras')
    <dl class="cifras">
        <div><dd>{{ $cifras['equipos'] }}</dd><dt>Equipos</dt></div>
        <div><dd>{{ $cifras['categorias'] }}</dd><dt>Categorías</dt></div>
        <div><dd>{{ $cifras['jugados'] }}</dd><dt>Partidos oficiales</dt></div>
        <div><dd>{{ $cifras['canchas'] }}</dd><dt>Canchas</dt></div>
    </dl>
@endsection

@section('cinta')
    @if ($recientes->isNotEmpty() || $partidosSemana->isNotEmpty())
        <div class="cinta" aria-hidden="true">
            <ul>
                @foreach ([1, 2] as $copia)
                    @foreach ($recientes as $partido)
                        <li><small>Final</small>{{ $partido->local?->nombre }} {{ $partido->cedula?->goles_local }}–{{ $partido->cedula?->goles_visitante }} {{ $partido->visitante?->nombre }}</li>
                    @endforeach
                    @foreach ($partidosSemana->whereNotIn('estado', ['finalizado', 'cancelado'])->take(6) as $partido)
                        <li><small>{{ $partido->fecha_partido?->locale('es')->translatedFormat('D') }} {{ $partido->hora_partido ? substr($partido->hora_partido, 0, 5) : '' }}</small>{{ $partido->local?->nombre }} vs. {{ $partido->visitante?->nombre }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif
@endsection

@section('content')
    <section class="sec" id="jornada" aria-labelledby="titulo-categorias">
        <p class="eyebrow">Elige tu categoría</p>
        <h2 class="display h2" id="titulo-categorias">Toda la liga, <em>a un toque.</em></h2>

        <div class="jornada-envoltura">
            <div class="jornada">
                @if ($categorias->isNotEmpty())
                    <fieldset class="cats" style="grid-column: 1 / -1; min-width: 0; border: 0;">
                        <legend class="saltar">Filtrar partidos por categoría</legend>
                        <input type="radio" name="categoria" id="cat-todas" checked>
                        <label class="cat" for="cat-todas">
                            <span class="c"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/></svg><b>{{ $partidosSemana->count() }}</b></span>Todas
                        </label>
                        @foreach ($categorias as $categoria)
                            <input type="radio" name="categoria" id="cat-{{ $categoria->id }}">
                            <label class="cat" for="cat-{{ $categoria->id }}">
                                <span class="c"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M8 3 3 6l2 5 3-1v11h8V10l3 1 2-5-5-3c-.5 1.5-2 2.5-4 2.5S8.5 4.5 8 3z"/></svg><b>{{ $partidosSemana->where('jornada.grupo.categoria_id', $categoria->id)->count() }}</b></span>{{ $categoria->nombre }}
                            </label>
                        @endforeach
                    </fieldset>
                @endif

                <div class="jl">
                    <h2 class="display h2">Jornada<br>{{ $jornadaNumero ?? '—' }}</h2>
                    <span class="raya" aria-hidden="true"></span>
                    <p>Semana del {{ $lunes->locale('es')->translatedFormat('j \d\e F') }} al {{ $lunes->addDays(6)->locale('es')->translatedFormat('j \d\e F') }}. Desliza para ver todos los partidos.</p>
                    <a class="enlace" href="{{ route('portal.programacion', ['semana' => $lunes->toDateString()]) }}">Programación completa <span class="flecha" aria-hidden="true">→</span></a>
                </div>

                @if ($partidosSemana->isEmpty())
                    <div class="vacio" role="status"><p>Todavía no hay partidos publicados.</p><p>La programación aparece aquí en cuanto la liga publica la jornada.</p></div>
                @else
                    <ul class="pista" aria-label="Partidos de la semana">
                        @foreach ($partidosSemana as $partido)
                            @php
                                $marcador = $marcadores->get($partido->id);
                                $categoriaPartido = $partido->jornada?->grupo?->categoria;
                            @endphp
                            <li class="mc tarjeta" data-c="{{ $categoriaPartido?->id }}">
                                <div class="arriba">
                                    @if ($partido->estado === 'en juego')
                                        <span class="tag vivo">En vivo</span>
                                    @elseif ($marcador)
                                        <span class="tag final">Final</span>
                                    @elseif (isset($etiquetaEstado[$partido->estado]))
                                        <span class="tag">{{ $etiquetaEstado[$partido->estado] }}</span>
                                    @elseif ($categoriaPartido)
                                        <span class="tag">{{ $categoriaPartido->nombre }}</span>
                                    @endif
                                    <x-portal.escudo :nombre="$partido->local?->nombre" :tamano="70" />
                                    @if ($marcador)
                                        <b class="display gol" aria-label="{{ $marcador->goles_local }} a {{ $marcador->goles_visitante }}">{{ $marcador->goles_local }}–{{ $marcador->goles_visitante }}</b>
                                    @else
                                        <b class="display vs">vs</b>
                                    @endif
                                    <x-portal.escudo :nombre="$partido->visitante?->nombre" :tamano="70" />
                                </div>
                                <div class="abajo">
                                    <p class="equipos">{{ $partido->local?->nombre ?? 'Por definir' }} vs. {{ $partido->visitante?->nombre ?? 'Por definir' }}</p>
                                    <p class="cuando">{{ $partido->fecha_partido?->locale('es')->translatedFormat('D j') }}{{ $partido->hora_partido ? ' · '.substr($partido->hora_partido, 0, 5).' h' : '' }}</p>
                                    <p class="donde">{{ $partido->cancha?->nombre ?? 'Cancha por confirmar' }}{{ $categoriaPartido ? ' · '.$categoriaPartido->nombre : '' }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    <section class="sec" aria-labelledby="titulo-consulta">
        <p class="eyebrow">Consulta rápida</p>
        <h2 class="display h2" id="titulo-consulta">Lo que <em>todos</em> buscan.</h2>
        <div class="cols">
            <a class="col c1 tarjeta" href="{{ route('portal.resultados') }}">
                <span class="fondo" aria-hidden="true"></span>
                <span class="numero-grande display" aria-hidden="true">01</span>
                <h3 class="display">Tabla<br>general</h3>
                <p>Posiciones, diferencia de goles y puntos de cada equipo.</p>
                @if ($primeraTabla->isNotEmpty())
                    <div class="pos" aria-hidden="true">
                        @foreach ($primeraTabla->take(3) as $fila)
                            <div><b>{{ $fila->posicion }}</b><x-portal.escudo :nombre="$fila->equipo?->nombre" :tamano="26" />{{ $fila->equipo?->nombre }}<em>{{ $fila->puntos }}</em></div>
                        @endforeach
                    </div>
                @endif
                <span class="enlace">Ver tablas <span class="flecha" aria-hidden="true">→</span></span>
            </a>
            <a class="col c2 tarjeta" href="{{ route('portal.castigados') }}">
                <span class="fondo" aria-hidden="true"></span>
                <span class="naipes" aria-hidden="true"><i class="y"></i><i class="r"></i></span>
                <h3 class="display">Boletín de<br>castigados</h3>
                <p>Quién no juega esta jornada y por qué.</p>
                <span class="contador">{{ $castigados === 1 ? '1 persona suspendida' : $castigados.' personas suspendidas' }}</span>
                <span class="enlace">Ver boletín <span class="flecha" aria-hidden="true">→</span></span>
            </a>
            <a class="col c3 tarjeta" href="{{ route('portal.buscar') }}">
                <span class="fondo" aria-hidden="true"></span>
                <svg class="playera" viewBox="0 0 200 200" aria-hidden="true" focusable="false">
                    <path d="M70 18 38 30 6 70l28 24 16-14v104h100V80l16 14 28-24-32-40-32-12q-30 28-60 0Z" fill="#fff"/>
                    <path d="M70 18q30 28 60 0l-8-3q-22 19-44 0Z" fill="#0d0f14" opacity=".85"/>
                    <text x="100" y="150" text-anchor="middle" font-family="Impact, 'Arial Narrow', sans-serif" font-style="italic" font-weight="900" font-size="64" fill="#0d0f14">7</text>
                </svg>
                <h3 class="display">Mi<br>equipo</h3>
                <p>Busca a tu equipo: sus partidos, resultados y castigos.</p>
                <span class="enlace">Buscar equipo <span class="flecha" aria-hidden="true">→</span></span>
            </a>
        </div>
    </section>

    <div class="ventajas">
        <div class="ventaja"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg><div><b>Al minuto</b><span>Resultados en cuanto la liga valida la cédula.</span></div></div>
        <div class="ventaja"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M5 11h14v10H5zM8 11V7a4 4 0 0 1 7.5-2"/></svg><div><b>Sin registro</b><span>Consulta todo sin crear cuenta.</span></div></div>
        <div class="ventaja"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M7 2h10v20H7zM11 18h2"/></svg><div><b>En tu celular</b><span>Hecho primero para el teléfono.</span></div></div>
        <div class="ventaja"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6zM9 12l2 2 4-4"/></svg><div><b>Dato oficial</b><span>Validado por la liga y sus comisiones.</span></div></div>
    </div>

    <section class="banner" aria-labelledby="titulo-banner">
        <div class="estelas" aria-hidden="true"></div>
        <div>
            <h2 class="display" id="titulo-banner"><span>Juega.</span><span>Compite.</span><span>Repite.</span></h2>
            <p>La tabla se actualiza cada vez que la liga valida una cédula. Cambia de grupo y mira quién manda.</p>
            <a class="btn btn-a" href="{{ route('portal.resultados') }}">Ver todas las tablas <span class="flecha" aria-hidden="true">→</span></a>
            <div class="energia">
                <div><b>{{ $cifras['jugados'] }}</b>partidos<br>oficiales</div>
                <div><b>{{ $goleo->sum('total') }}</b>goles de los<br>líderes de goleo</div>
                <div><b>{{ $grupos->count() }}</b>tablas<br>en juego</div>
            </div>
        </div>
        <div class="tablero">
            @if ($grupos->isEmpty())
                <p>Las tablas aparecen en cuanto la liga valida el primer resultado.</p>
            @else
                <div class="pestanas" role="group" aria-label="Elegir tabla">
                    @foreach ($grupos as $grupo)
                        <input type="radio" name="tabla" id="tab-{{ $grupo->id }}" @checked($loop->first)>
                        <label for="tab-{{ $grupo->id }}">{{ $grupo->categoria?->nombre }} · {{ $grupo->nombre }}</label>
                    @endforeach
                </div>
                @foreach ($grupos as $grupo)
                    <div class="panel-tabla" id="panel-{{ $grupo->id }}">
                        <h3>{{ $grupo->categoria?->nombre }} · {{ $grupo->nombre }}</h3>
                        <div class="fila cabeza"><span>#</span><span>Equipo</span><span class="num">PJ</span><span class="num">DG</span><span class="num">PTS</span></div>
                        @foreach ($tablas[$grupo->id] ?? [] as $fila)
                            <div class="fila {{ $loop->first ? 'lider' : '' }}">
                                <span class="p">{{ $fila->posicion }}</span>
                                <span class="eq"><x-portal.escudo :nombre="$fila->equipo?->nombre" :tamano="24" /><span>{{ $fila->equipo?->nombre }}</span></span>
                                <span class="num">{{ $fila->pj }}</span>
                                <span class="num">{{ $fila->dg > 0 ? '+'.$fila->dg : $fila->dg }}</span>
                                <span class="pts">{{ $fila->puntos }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @endif
        </div>
    </section>

    <section class="goleo" aria-labelledby="titulo-goleo">
        <div>
            <p class="eyebrow">#OrgulloMorelia</p>
            <h2 class="display h2" id="titulo-goleo">Los que<br>la meten.</h2>
            <p style="margin: .625rem 0 0; color: var(--texto-secundario); font-size: .875rem;">Goleo de la liga con resultados oficiales.</p>
        </div>
        @if ($goleo->isEmpty())
            <p class="vacio" style="margin: 0;">Aún no hay goles en resultados oficiales.</p>
        @else
            <ul class="jugadores">
                @foreach ($goleo as $fila)
                    @php
                        $tonoJugador = crc32($fila['equipo']) % 360;
                    @endphp
                    <li class="pl" style="background: linear-gradient(160deg, hsl({{ $tonoJugador }} 62% 32%), #0b0c0f);">
                        <span class="g">{{ $fila['total'] }}<small>{{ $fila['total'] === 1 ? 'gol' : 'goles' }}</small></span>
                        <svg viewBox="0 0 200 200" aria-hidden="true" focusable="false"><path d="M70 18 38 30 6 70l28 24 16-14v104h100V80l16 14 28-24-32-40-32-12q-30 28-60 0Z" fill="hsl({{ $tonoJugador }} 62% 38%)" stroke="hsl({{ ($tonoJugador + 40) % 360 }} 85% 72%)" stroke-width="5"/><text x="100" y="150" text-anchor="middle" font-family="Impact, 'Arial Narrow', sans-serif" font-style="italic" font-weight="900" font-size="70" fill="hsl({{ ($tonoJugador + 40) % 360 }} 85% 72%)">{{ $loop->iteration }}</text></svg>
                        <b>{{ $fila['nombre'] }}</b>
                        <small>{{ $fila['equipo'] }}</small>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="invita" aria-labelledby="titulo-invita">
        <div>
            <h2 class="display" id="titulo-invita">¿Eres de un club?</h2>
            <p>Delegados, presidentes y jugadores ven su club, sus partidos y sus castigos desde «Mi club».</p>
        </div>
        @guest
            <a class="btn btn-a" href="{{ route('inicio') }}">Iniciar sesión <span class="flecha" aria-hidden="true">→</span></a>
        @else
            <a class="btn btn-a" href="{{ auth()->user()->rutaInicio() }}">Ir al panel <span class="flecha" aria-hidden="true">→</span></a>
        @endguest
    </section>
@endsection
