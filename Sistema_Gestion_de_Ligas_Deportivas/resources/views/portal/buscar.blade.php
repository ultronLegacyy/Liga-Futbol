@extends('layouts.portal')

@section('title', 'Buscar')

@section('descripcion', 'Busca partidos, resultados, castigados y comunicados de la liga por fecha, categoría o equipo.')

@section('estilos')
    .buscador {
        display: grid;
        gap: .875rem;
        margin-top: 1.25rem;
        padding: 1rem;
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, .12);
    }

    .buscador label { display: grid; gap: .375rem; font-size: .875rem; font-weight: 700; }

    .buscador input, .buscador select {
        min-height: 44px;
        width: 100%;
        padding: .5rem .875rem;
        border: 2px solid transparent;
        border-radius: .75rem;
        background: #fff;
        color: var(--texto);
        font: inherit;
        font-size: 1rem;
    }

    .buscador input:focus-visible, .buscador select:focus-visible { outline: 3px solid var(--foco); outline-offset: 1px; }

    .acciones { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }

    .buscador button {
        min-height: 44px;
        padding: 0 1.5rem;
        border: 0;
        border-radius: 999px;
        background: var(--lima);
        color: var(--tinta);
        font: inherit;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        cursor: pointer;
    }

    .buscador button:hover { background: var(--blanco); }
    .buscador .limpiar { color: #fff; font-weight: 600; }

    .seccion-titulo span { display: inline-block; padding-bottom: .375rem; border-bottom: 4px solid var(--acento); }
    .cuantos { margin: -.25rem 0 1rem; color: var(--texto-secundario); }

    .lista-resultados { display: grid; gap: .75rem; margin: 0; padding: 0; list-style: none; }
    .fila { display: grid; gap: .25rem; padding: 1rem 1.125rem; overflow-wrap: anywhere; }
    .fila p { margin: 0; }
    .fila .principal { font-weight: 800; font-size: 1.0625rem; }
    .fila .marcador { font-family: var(--display); font-weight: 800; font-size: 1.375rem; color: var(--royal-oscuro); }
    .fila .secundario { color: var(--texto-secundario); font-size: .9375rem; }

    @media (min-width: 48rem) {
        .buscador { grid-template-columns: minmax(0, 2fr) minmax(0, 1.4fr) minmax(0, 1fr); align-items: end; }
        .acciones { grid-column: 1 / -1; }
        .lista-resultados { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
@endsection

@section('hero')
    <p class="etiqueta-hero"><span class="punto" aria-hidden="true"></span>Buscador</p>
    <h1 class="display">Buscar en la liga</h1>
    <span class="linea" aria-hidden="true"></span>
    <p>Encuentra partidos, resultados, castigados y comunicados por equipo, categoría o fecha.</p>

    <form class="buscador" method="GET" action="{{ route('portal.buscar') }}" role="search" aria-label="Buscar en el portal">
        <label for="equipo">Equipo
            <input id="equipo" name="equipo" type="search" maxlength="60" value="{{ $filtros['equipo'] }}" placeholder="Ej. Halcones" autocomplete="off">
        </label>
        <label for="categoria">Categoría
            <select id="categoria" name="categoria">
                <option value="">Todas</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected($filtros['categoria'] === $categoria->id)>{{ $categoria->nombre }} ({{ $categoria->rama }})</option>
                @endforeach
            </select>
        </label>
        <label for="fecha">Fecha
            <input id="fecha" name="fecha" type="date" value="{{ $filtros['fecha'] }}">
        </label>
        <div class="acciones">
            <button type="submit">Buscar</button>
            @if ($hayFiltros)
                <a class="limpiar" href="{{ route('portal.buscar') }}">Limpiar filtros</a>
            @endif
        </div>
    </form>
@endsection

@section('content')
    @if (! $hayFiltros)
        <div class="vacio" role="status">
            <p>Escribe el nombre de un equipo, elige una categoría o una fecha para empezar.</p>
            <p>Solo se busca en lo publicado por la liga.</p>
        </div>
    @else
        @php
            $total = $partidos->count() + $castigados->count() + $comunicados->count();
        @endphp
        <p class="cuantos" role="status">{{ $total === 1 ? '1 resultado' : $total.' resultados' }}</p>

        @if ($total === 0)
            <div class="vacio">
                <p>No encontramos nada con esos filtros.</p>
                <p>Prueba con otra fecha, otra categoría o solo una parte del nombre del equipo.</p>
            </div>
        @endif

        @if ($partidos->isNotEmpty())
            <section aria-labelledby="partidos-titulo">
                <h2 class="display seccion-titulo" id="partidos-titulo"><span>Partidos</span></h2>
                <ul class="lista-resultados">
                    @foreach ($partidos as $partido)
                        @php
                            $categoria = $partido->jornada?->grupo?->categoria;
                            $oficial = $partido->cedula;
                        @endphp
                        <li class="fila tarjeta">
                            <p class="principal">
                                {{ $partido->local?->nombre ?? 'Por definir' }}
                                @if ($oficial)
                                    <span class="marcador">{{ $oficial->goles_local }} – {{ $oficial->goles_visitante }}</span>
                                @else
                                    <span class="secundario">vs.</span>
                                @endif
                                {{ $partido->visitante?->nombre ?? 'Por definir' }}
                            </p>
                            <p class="secundario">
                                <time datetime="{{ $partido->fecha_partido?->toDateString() }}">{{ $partido->fecha_partido?->format('d/m/Y') }}</time>@if ($partido->hora_partido), {{ substr((string) $partido->hora_partido, 0, 5) }} h @endif
                                @if ($partido->cancha) · {{ $partido->cancha->nombre }}@endif
                            </p>
                            <p class="secundario">
                                @if ($categoria){{ $categoria->nombre }} ({{ $categoria->rama }}) · @endif Jornada {{ $partido->jornada?->numero_jornada }} · {{ $oficial ? 'Resultado oficial' : 'Programado' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($castigados->isNotEmpty())
            <section aria-labelledby="castigados-titulo">
                <h2 class="display seccion-titulo" id="castigados-titulo"><span>Castigados</span></h2>
                <ul class="lista-resultados">
                    @foreach ($castigados as $sancion)
                        <li class="fila tarjeta">
                            <p class="principal">{{ $sancion->persona ? trim($sancion->persona->nombre.' '.$sancion->persona->apellidos) : $sancion->equipo_publico }}</p>
                            <p class="secundario">{{ $sancion->equipo_publico }}</p>
                            <p class="secundario">
                                @if ($sancion->tipo_sancion === \App\Models\Sancion::TIPO_PARTIDOS)
                                    {{ $sancion->partidosRestantes() }} {{ $sancion->partidosRestantes() === 1 ? 'partido pendiente' : 'partidos pendientes' }}
                                @else
                                    Suspendido hasta el {{ $sancion->fecha_fin?->format('d/m/Y') }}
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
                <p><a href="{{ route('portal.castigados') }}">Ver el boletín completo</a></p>
            </section>
        @endif

        @if ($comunicados->isNotEmpty())
            <section aria-labelledby="comunicados-titulo">
                <h2 class="display seccion-titulo" id="comunicados-titulo"><span>Comunicados</span></h2>
                <ul class="lista-resultados">
                    @foreach ($comunicados as $comunicado)
                        <li class="fila tarjeta">
                            @php
                                $publicadoLocal = $comunicado->publicado_en?->copy()->timezone(\App\Models\Sancion::ZONA_HORARIA);
                            @endphp
                            <p class="secundario"><time datetime="{{ $publicadoLocal?->toDateString() }}">{{ $publicadoLocal?->format('d/m/Y') }}</time></p>
                            <p class="principal">{{ $comunicado->titulo }}</p>
                            <p>{{ \Illuminate\Support\Str::limit($comunicado->contenido, 180) }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
@endsection
