@extends('layouts.portal')

@section('title', 'Resultados y tablas')

@section('descripcion', 'Resultados oficiales, tabla de posiciones, goleo y tarjetas de la Liga Municipal de Fútbol.')

@section('estilos')
    .periodo { margin: 0; font-size: 1.0625rem; font-weight: 600; color: var(--blanco) !important; }
    .grupos { margin-top: 1.25rem; }
    

    .seccion-titulo span { display: inline-block; padding-bottom: .375rem; border-bottom: 4px solid var(--acento); }

    .tabla-envoltura { overflow-x: auto; }

    table.posiciones { width: 100%; border-collapse: collapse; font-size: .9375rem; }
    .posiciones caption { padding: .875rem 1rem; text-align: left; color: var(--texto-secundario); font-size: .875rem; }
    .posiciones th, .posiciones td { padding: .625rem .5rem; border-top: 1px solid var(--borde); text-align: right; white-space: nowrap; }
    .posiciones th { color: var(--texto-secundario); font-size: .75rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .posiciones th:nth-child(2), .posiciones td:nth-child(2) { text-align: left; white-space: normal; }
    .posiciones .pos { font-family: var(--display); font-weight: 800; font-size: 1.375rem; text-align: center; width: 2.75rem; }
    .posiciones .pts { font-family: var(--display); font-weight: 800; font-size: 1.375rem; color: var(--royal-oscuro); }
    .posiciones tbody tr:first-child { background: #f7fde0; }
    .posiciones tbody tr:first-child .pos { color: var(--tinta); }
    .posiciones .equipo { font-weight: 700; }
    .solo-ancho { display: none; }

    .resultados { display: grid; gap: .75rem; margin: 0; padding: 0; list-style: none; }

    .resultado {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        gap: .75rem;
        padding: 1rem 1.125rem;
    }

    .resultado .equipo { font-weight: 700; line-height: 1.3; overflow-wrap: anywhere; }
    .resultado .visitante { text-align: right; }
    .resultado .marcador { font-family: var(--display); font-weight: 800; font-size: 2rem; line-height: 1; white-space: nowrap; text-align: center; }
    .resultado .gana { color: var(--marino); }
    .resultado .datos { grid-column: 1 / -1; margin: 0; color: var(--texto-secundario); font-size: .875rem; }

    .lideres { display: grid; gap: 1rem; }
    .lista { margin: 0; padding: 0; list-style: none; }
    .lista li { display: grid; grid-template-columns: 2rem minmax(0, 1fr) auto; align-items: center; gap: .75rem; padding: .75rem 1rem; border-top: 1px solid var(--borde); }
    .lista li:first-child { border-top: 0; }
    .lista .n { font-family: var(--display); font-weight: 800; font-size: 1.25rem; color: var(--texto-secundario); }
    .lista .quien b { display: block; }
    .lista .quien span { color: var(--texto-secundario); font-size: .875rem; }
    .lista .total { font-family: var(--display); font-weight: 800; font-size: 1.625rem; }
    .lista h3 { margin: 0; padding: 1rem 1rem .5rem; font-size: 1rem; }
    .tarjeta-amarilla, .tarjeta-roja { display: inline-block; min-width: 1.75rem; padding: .125rem .375rem; border-radius: .25rem; font-weight: 800; text-align: center; }
    .tarjeta-amarilla { background: #ffd43b; color: #3d2f00; }
    .tarjeta-roja { background: #c4121b; color: #fff; }

    @media (min-width: 40rem) {
        .solo-ancho { display: table-cell; }
        .resultados { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .lideres { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
@endsection

@section('hero')
    <p class="etiqueta-hero"><span class="punto" aria-hidden="true"></span>Resultados oficiales</p>
    <h1 class="display">Resultados y tablas</h1>
    <span class="linea" aria-hidden="true"></span>
    @if ($grupo)
        <p class="periodo">{{ $grupo->categoria?->nombre }}{{ $grupo->categoria?->rama ? ' ('.$grupo->categoria->rama.')' : '' }} · {{ $grupo->nombre }}{{ $grupo->temporada ? ' · '.$grupo->temporada->nombre : '' }}</p>
    @else
        <p class="periodo">Aún no hay resultados oficiales publicados.</p>
    @endif

    @if ($grupos->count() > 1)
        <nav aria-label="Elegir categoría y grupo">
            <ul class="chips grupos">
                @foreach ($grupos as $opcion)
                    <li>
                        <a href="{{ route('portal.resultados', ['grupo' => $opcion->id]) }}" @if ($grupo && $opcion->id === $grupo->id) aria-current="page" @endif>
                            {{ $opcion->categoria?->nombre }} · {{ $opcion->nombre }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
@endsection

@section('content')
    @if (! $grupo)
        <div class="vacio" role="status">
            <p>Todavía no hay resultados oficiales.</p>
            <p>Los resultados aparecen aquí cuando la liga valida la cédula de cada partido. Mientras tanto consulta la <a href="{{ route('portal.programacion') }}">programación</a>.</p>
        </div>
    @else
        <section aria-labelledby="tabla-titulo">
            <h2 class="display seccion-titulo" id="tabla-titulo"><span>Tabla de posiciones</span></h2>
            <div class="tarjeta tabla-envoltura">
                <table class="posiciones">
                    <caption>Calculada solo con resultados validados por la liga.</caption>
                    <thead>
                        <tr>
                            <th scope="col"><abbr title="Posición">Pos</abbr></th>
                            <th scope="col">Equipo</th>
                            <th scope="col"><abbr title="Partidos jugados">PJ</abbr></th>
                            <th scope="col" class="solo-ancho"><abbr title="Ganados">G</abbr></th>
                            <th scope="col" class="solo-ancho"><abbr title="Empatados">E</abbr></th>
                            <th scope="col" class="solo-ancho"><abbr title="Perdidos">P</abbr></th>
                            <th scope="col" class="solo-ancho"><abbr title="Goles a favor">GF</abbr></th>
                            <th scope="col" class="solo-ancho"><abbr title="Goles en contra">GC</abbr></th>
                            <th scope="col"><abbr title="Diferencia de goles">DG</abbr></th>
                            <th scope="col"><abbr title="Puntos">PTS</abbr></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tabla as $fila)
                            <tr>
                                <td class="pos">{{ $fila->posicion }}</td>
                                <td class="equipo">{{ $fila->equipo?->nombre }}</td>
                                <td>{{ $fila->pj }}</td>
                                <td class="solo-ancho">{{ $fila->pg }}</td>
                                <td class="solo-ancho">{{ $fila->pe }}</td>
                                <td class="solo-ancho">{{ $fila->pp }}</td>
                                <td class="solo-ancho">{{ $fila->gf }}</td>
                                <td class="solo-ancho">{{ $fila->gc }}</td>
                                <td>{{ $fila->dg > 0 ? '+'.$fila->dg : $fila->dg }}</td>
                                <td class="pts">{{ $fila->puntos }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="resultados-titulo">
            <h2 class="display seccion-titulo" id="resultados-titulo"><span>Resultados</span></h2>
            @if ($partidos->isEmpty())
                <div class="vacio" role="status"><p>Sin resultados oficiales en este grupo.</p></div>
            @else
                <ul class="resultados">
                    @foreach ($partidos as $partido)
                        @php
                            $golesLocal = (int) $partido->cedula?->goles_local;
                            $golesVisitante = (int) $partido->cedula?->goles_visitante;
                        @endphp
                        <li class="resultado tarjeta">
                            <span class="equipo {{ $golesLocal > $golesVisitante ? 'gana' : '' }}">{{ $partido->local?->nombre }}</span>
                            <span class="marcador" aria-label="{{ $golesLocal }} a {{ $golesVisitante }}">{{ $golesLocal }} – {{ $golesVisitante }}</span>
                            <span class="equipo visitante {{ $golesVisitante > $golesLocal ? 'gana' : '' }}">{{ $partido->visitante?->nombre }}</span>
                            <p class="datos">
                                Jornada {{ $partido->jornada?->numero_jornada }}@if ($partido->fecha_partido) · <time datetime="{{ $partido->fecha_partido->toDateString() }}">{{ $partido->fecha_partido->format('d/m/Y') }}</time>@endif
                                @if ($partido->cancha) · {{ $partido->cancha->nombre }}@endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="estadisticas-titulo">
            <h2 class="display seccion-titulo" id="estadisticas-titulo"><span>Estadísticas</span></h2>
            <div class="lideres">
                <div class="tarjeta">
                    <h3>Goleo</h3>
                    @if ($estadisticas['goleo']->isEmpty())
                        <p class="vacio" style="margin: 0 1rem 1rem;">Aún no hay goles registrados.</p>
                    @else
                        <ol class="lista">
                            @foreach ($estadisticas['goleo'] as $fila)
                                <li>
                                    <span class="n">{{ $loop->iteration }}</span>
                                    <span class="quien"><b>{{ $fila['nombre'] }}</b><span>{{ $fila['equipo'] }}</span></span>
                                    <span class="total">{{ $fila['total'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
                <div class="tarjeta">
                    <h3>Tarjetas</h3>
                    @if ($estadisticas['tarjetas']->isEmpty())
                        <p class="vacio" style="margin: 0 1rem 1rem;">Aún no hay tarjetas registradas.</p>
                    @else
                        <ol class="lista">
                            @foreach ($estadisticas['tarjetas'] as $fila)
                                <li>
                                    <span class="n">{{ $loop->iteration }}</span>
                                    <span class="quien"><b>{{ $fila['nombre'] }}</b><span>{{ $fila['equipo'] }}</span></span>
                                    <span>
                                        <span class="tarjeta-amarilla" aria-label="{{ $fila['amarillas'] }} amarillas">{{ $fila['amarillas'] }}</span>
                                        <span class="tarjeta-roja" aria-label="{{ $fila['rojas'] }} rojas">{{ $fila['rojas'] }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </section>
    @endif
@endsection
