
@props([
    'type' => 'info',
    'message' => null,
])

@php
    $tipos = [
        'success' => [
            'clases' => 'bg-green-500/10 border-green-500/30 text-green-400',
            'icono' => '✓',
            'rol' => 'status',
        ],
        'error' => [
            'clases' => 'bg-red-500/10 border-red-500/30 text-red-400',
            'icono' => '!',
            'rol' => 'alert',
        ],
        'info' => [
            'clases' => 'bg-blue-500/10 border-blue-500/30 text-blue-400',
            'icono' => 'i',
            'rol' => 'status',
        ],
    ];

    $tipoActual = array_key_exists($type, $tipos) ? $type : 'info';
    $estilo = $tipos[$tipoActual];
@endphp

@if (filled($message) || isset($slot))
    <div
        role="{{ $estilo['rol'] }}"
        @if ($tipoActual === 'error') aria-live="assertive"
        @else aria-live="polite" @endif
        {{ $attributes->merge([
            'class' => 'mb-6 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm '.$estilo['clases']
        ]) }}
    >
        <span
            aria-hidden="true"
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-current font-bold"
        >{{ $estilo['icono'] }}</span>

        <div class="min-w-0">
            @if (filled($message))
                {{ $message }}
            @else
                {{ $slot }}
            @endif
        </div>
    </div>
@endif
