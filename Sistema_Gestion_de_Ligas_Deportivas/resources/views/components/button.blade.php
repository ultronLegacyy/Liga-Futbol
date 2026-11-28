{{-- HAB-S7 · S7-T01: botón base. Mide al menos 44 px de alto (RNF-13) y usa el azul eléctrico como acción principal.
     Con «href» se pinta como enlace; sin él, como <button>. --}}
@props([
    'variante' => 'primario', // primario, secundario, peligro
    'href' => null,
    'type' => 'button',
])

@php
    $colores = match ($variante) {
        'secundario' => 'border border-gray-600 bg-transparent text-white hover:border-celeste hover:text-celeste',
        'peligro' => 'bg-red-700 text-white hover:bg-red-600',
        default => 'bg-electrico text-white hover:bg-electrico-claro',
    };
    $clases = "inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold transition {$colores}";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</button>
@endif
