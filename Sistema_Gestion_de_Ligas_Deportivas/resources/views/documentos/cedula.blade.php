@extends('documentos._base', ['titulo' => 'Cédula oficial - partido '.$partido->id])

@php
    $nombre = fn ($jugador): string => $jugador?->persona ? trim($jugador->persona->nombre.' '.$jugador->persona->apellidos) : 'Jugador sin registro';
    $categoria = $partido->jornada?->grupo?->categoria;
    $lados = [
        'local' => ['equipo' => $partido->local, 'id' => $partido->equipo_local_id],
        'visitante' => ['equipo' => $partido->visitante, 'id' => $partido->equipo_visitante_id],
    ];
    // Cada gol o tarjeta se atribuye al equipo de la fila del jugador en la alineación de esta cédula, como hace
    // ValidacionCedulaService (HU-040), no al equipo que el jugador tiene hoy
    $equipoEnAlineacion = $cedula->alineaciones->whereNotNull('jugador_id')->mapWithKeys(fn ($fila) => [(int) $fila->jugador_id => (int) $fila->equipo_id]);
    $equipoDe = fn (?int $equipoId): string => $equipoId === (int) $partido->equipo_local_id
        ? ($partido->local->nombre ?? 'Local')
        : ($equipoId === (int) $partido->equipo_visitante_id ? ($partido->visitante->nombre ?? 'Visitante') : '—');
@endphp

@section('documento')
    <h1>Cédula arbitral oficial</h1>
    <p class="sub">Resultado del partido según la cédula cerrada{{ $cedula->esDePapel() ? ' (transcrita desde la cédula en papel)' : '' }}.</p>

    <table class="datos">
        <tr><td class="et">Fecha y hora</td><td>{{ $partido->fecha_partido ? \Carbon\Carbon::parse($partido->fecha_partido)->format('d/m/Y') : 'Sin fecha' }}{{ $partido->hora_partido ? ', '.substr((string) $partido->hora_partido, 0, 5).' h' : '' }}</td></tr>
        @if ($categoria)
            <tr><td class="et">Categoría</td><td>{{ $categoria->nombre }} ({{ $categoria->rama }}){{ $partido->jornada?->grupo?->nombre ? ' · '.$partido->jornada->grupo->nombre : '' }}</td></tr>
        @endif
        <tr><td class="et">Jornada</td><td>{{ $partido->jornada?->numero_jornada ?? '—' }}</td></tr>
        <tr><td class="et">Cancha</td><td>{{ $partido->cancha?->nombre ?? 'Sin cancha' }}</td></tr>
        <tr><td class="et">Árbitro</td><td>{{ $partido->arbitro?->persona ? trim($partido->arbitro->persona->nombre.' '.$partido->arbitro->persona->apellidos) : 'Sin árbitro' }}</td></tr>
        <tr><td class="et">Cerrada</td><td>{{ $cedula->cerrada_en?->timezone('America/Mexico_City')->format('d/m/Y H:i') }} h{{ $cedula->cerradaPor ? ' · '.$cedula->cerradaPor->name : '' }}</td></tr>
    </table>

    <table class="marcador">
        <tr>
            <td>{{ $partido->local->nombre ?? 'Local' }}</td>
            <td class="goles">{{ $cedula->goles_local }}</td>
            <td class="goles">{{ $cedula->goles_visitante }}</td>
            <td>{{ $partido->visitante->nombre ?? 'Visitante' }}</td>
        </tr>
    </table>

    <h2>Alineaciones</h2>
    <table class="tabla">
        <tr>
            @foreach ($lados as $lado)
                <td style="width: 50%; border: 0; padding: 0 6px 0 0;">
                    <table class="tabla">
                        <thead><tr><th colspan="3">{{ $lado['equipo']->nombre ?? ucfirst($loop->first ? 'local' : 'visitante') }}</th></tr><tr><th class="num">#</th><th>Jugador</th><th>Asistió</th></tr></thead>
                        <tbody>
                            @forelse ($cedula->alineaciones->where('equipo_id', $lado['id']) as $fila)
                                <tr>
                                    <td class="num">{{ $fila->numero_camiseta ?? '—' }}</td>
                                    <td>{{ $nombre($fila->jugador) }}{{ $fila->es_capitan ? ' (C)' : '' }}</td>
                                    <td>{{ $fila->asistio ? 'Sí' : 'No' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">Sin alineación registrada.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>Goles y tarjetas</h2>
    @if ($cedula->eventos->isEmpty())
        <p class="vacio">No se registraron goles ni tarjetas.</p>
    @else
        <table class="tabla">
            <thead><tr><th class="num">Minuto</th><th>Tipo</th><th>Jugador</th><th>Equipo</th></tr></thead>
            <tbody>
                @foreach ($cedula->eventos as $evento)
                    <tr>
                        <td class="num">{{ $evento->minuto !== null ? $evento->minuto."'" : '—' }}</td>
                        <td>{{ $evento->tipo === 'Amarilla' ? 'Tarjeta amarilla' : ($evento->tipo === 'Roja' ? 'Tarjeta roja' : 'Gol') }}{{ $evento->autogol ? ' (autogol)' : '' }}</td>
                        <td>{{ $nombre($evento->jugador) }}</td>
                        <td>{{ $equipoDe($evento->jugador_id !== null ? $equipoEnAlineacion->get((int) $evento->jugador_id) : null) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Incidencias</h2>
    @if ($cedula->incidencias->isEmpty())
        <p class="vacio">Sin incidencias.</p>
    @else
        <table class="tabla">
            <thead><tr><th class="num">Minuto</th><th>Equipo</th><th>Descripción</th></tr></thead>
            <tbody>
                @foreach ($cedula->incidencias as $incidencia)
                    <tr>
                        <td class="num">{{ $incidencia->minuto !== null ? $incidencia->minuto."'" : '—' }}</td>
                        <td>{{ $incidencia->equipo?->nombre ?? 'General' }}</td>
                        <td>{{ $incidencia->descripcion }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Informe del árbitro</h2>
    <p>{!! nl2br(e($cedula->informe_arbitral ?: 'Sin informe.')) !!}</p>

    <table class="firmas">
        <tr>
            @foreach (\App\Services\CierreCedulaService::LADOS as $lado)
                <td>
                    @isset($firmas[$lado])
                        <img src="{{ $firmas[$lado] }}" alt="Firma del capitán {{ $lado }}">
                    @elseif ($cedula->esDePapel())
                        <p>Firma en la cédula en papel</p>
                    @endisset
                    <div class="linea">Capitán {{ $lado }} · {{ $lado === 'local' ? ($partido->local->nombre ?? '') : ($partido->visitante->nombre ?? '') }}</div>
                </td>
            @endforeach
        </tr>
    </table>
@endsection
