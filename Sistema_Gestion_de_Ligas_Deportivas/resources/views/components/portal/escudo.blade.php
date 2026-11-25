@props(['nombre' => '', 'tamano' => 78])
{{-- Escudo genérico del prototipo: siglas del equipo y colores estables calculados de su nombre, en SVG --}}
@php
    $palabras = preg_split('/\s+/u', trim((string) $nombre), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $sigla = mb_strtoupper(implode('', array_map(fn (string $palabra): string => mb_substr($palabra, 0, 1), array_slice($palabras, 0, 3))));
    if (mb_strlen($sigla) < 2) {
        $sigla = mb_strtoupper(mb_substr($palabras[0], 0, 3));
    }
    $tono = crc32((string) $nombre) % 360;
    $fondo = "hsl({$tono} 62% 30%)";
    $borde = 'hsl('.(($tono + 40) % 360).' 85% 72%)';
@endphp
<svg viewBox="0 0 80 92" width="{{ $tamano }}" height="{{ round($tamano * 92 / 80) }}" {{ $attributes->merge(['class' => 'escudo-svg']) }} aria-hidden="true" focusable="false">
    <path d="M40 2 76 14v30c0 24-16 38-36 46C20 82 4 68 4 44V14Z" fill="{{ $fondo }}" stroke="{{ $borde }}" stroke-width="3"/>
    <path d="M4 40h72v8H4Z" fill="{{ $borde }}" opacity=".9"/>
    <text x="40" y="34" text-anchor="middle" font-family="Impact, 'Arial Narrow', sans-serif" font-style="italic" font-weight="900" font-size="20" fill="#fff">{{ $sigla }}</text>
    <circle cx="40" cy="66" r="9" fill="none" stroke="{{ $borde }}" stroke-width="2.5"/>
</svg>
