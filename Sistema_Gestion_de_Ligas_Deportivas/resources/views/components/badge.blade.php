@props([
    'variant' => 'info', // success, danger, warning, info, gray
    'size' => 'sm',
])

@php
    $classes = match ($variant) {
        'success' => 'bg-green-500/20 text-green-400 border-green-500/30',
        'danger' => 'bg-red-500/20 text-red-400 border-red-500/30',
        'warning' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
        'info' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
        default => 'bg-gray-700/60 text-gray-300 border-gray-600',
    };
    $sizeClass = $size === 'lg' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center font-medium rounded-full border {$classes} {$sizeClass}"]) }}>
    {{ $slot }}
</span>
