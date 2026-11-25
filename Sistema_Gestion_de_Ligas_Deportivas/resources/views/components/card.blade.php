{{-- HAB-S7 · S7-T01: tarjeta base del panel (esquinas redondeadas, fondo marino y borde suave). --}}
@props([
    'titulo' => null,
    'variante' => 'base', // base, destacada
])

@php
    $fondo = $variante === 'destacada'
        ? 'bg-gradient-to-br from-marino-800 to-electrico text-white border-electrico/40'
        : 'bg-gray-800 text-gray-100 border-gray-700';
@endphp

<section {{ $attributes->merge(['class' => "rounded-2xl border p-5 shadow-sm sm:p-6 {$fondo}"]) }}>
    @if ($titulo)
        <h2 class="mb-4 text-lg font-bold text-white">{{ $titulo }}</h2>
    @endif
    {{ $slot }}
</section>
