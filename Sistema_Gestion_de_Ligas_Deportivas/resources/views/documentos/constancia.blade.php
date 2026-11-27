@extends('documentos._base', ['titulo' => 'Constancia de registro'])

@php
    $persona = $jugador->persona;
    $nombreCompleto = $persona ? trim($persona->nombre.' '.$persona->apellidos) : 'Jugador';
    $categoria = $jugador->equipo?->grupo?->categoria;
@endphp

@section('documento')
    <h1>Constancia de registro</h1>
    <p class="sub">Constancia de que la persona está registrada como jugador de la liga.</p>

    <p style="font-size: 12px; margin: 18px 0;">
        La Liga Municipal de Fútbol Amateur de Morelia hace constar que <b>{{ $nombreCompleto }}</b> está registrado
        como jugador del equipo <b>{{ $jugador->equipo?->nombre ?? 'sin equipo' }}</b>{{ $jugador->equipo?->club ? ', del club '.$jugador->equipo->club->nombre : '' }},
        con el estado de elegibilidad <b>{{ $jugador->estado_elegibilidad ?? 'sin estado' }}</b> a la fecha de emisión.
    </p>

    <table class="datos">
        <tr><td class="et">Jugador</td><td>{{ $nombreCompleto }}</td></tr>
        <tr><td class="et">Dorsal</td><td>{{ $jugador->dorsal ?? '—' }}</td></tr>
        <tr><td class="et">Equipo</td><td>{{ $jugador->equipo?->nombre ?? '—' }}</td></tr>
        <tr><td class="et">Club</td><td>{{ $jugador->equipo?->club?->nombre ?? '—' }}</td></tr>
        @if ($categoria)
            <tr><td class="et">Categoría</td><td>{{ $categoria->nombre }} ({{ $categoria->rama }}){{ $categoria->temporada ? ' · '.$categoria->temporada->nombre : '' }}</td></tr>
        @endif
        <tr><td class="et">Elegibilidad</td><td>{{ $jugador->estado_elegibilidad ?? '—' }}</td></tr>
        @if ($jugador->fecha_baja)
            <tr><td class="et">Fecha de baja</td><td>{{ $jugador->fecha_baja->format('d/m/Y') }}</td></tr>
        @endif
    </table>

    <p>Se extiende a petición de la persona interesada para los fines que le convengan.</p>

    <table class="firmas" style="margin-top: 60px;">
        <tr>
            <td><div class="linea">Secretaría de la liga</div></td>
            <td><div class="linea">Tesorería de la liga</div></td>
        </tr>
    </table>
@endsection
