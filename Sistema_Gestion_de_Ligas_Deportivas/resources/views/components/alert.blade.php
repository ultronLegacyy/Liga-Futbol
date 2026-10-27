
@props([
    'type' => 'info',
    'message' => null,
    'dismissible' => false,
])

@php
    $tipos = [
        'success' => [
            'clases' => 'bg-green-500/10 border-green-500/30 text-green-400',
            'icono' => '✓',
            'etiqueta' => 'Éxito',
            'rol' => 'status',
        ],
        'error' => [
            'clases' => 'bg-red-500/10 border-red-500/30 text-red-400',
            'icono' => '!',
            'etiqueta' => 'Error',
            'rol' => 'alert',
        ],
        'danger' => [
            'clases' => 'bg-red-500/10 border-red-500/30 text-red-400',
            'icono' => '!',
            'etiqueta' => 'Error',
            'rol' => 'alert',
        ],
        'warning' => [
            'clases' => 'bg-amber-500/10 border-amber-500/30 text-amber-400',
            'icono' => '!',
            'etiqueta' => 'Atención',
            'rol' => 'status',
        ],
        'info' => [
            'clases' => 'bg-blue-500/10 border-blue-500/30 text-blue-400',
            'icono' => 'i',
            'etiqueta' => 'Información',
            'rol' => 'status',
        ],
    ];

    $tipoActual = array_key_exists($type, $tipos) ? $type : 'info';
    $estilo = $tipos[$tipoActual];
    $mensaje = $message;
@endphp

@if (filled($mensaje) || trim((string) $slot) !== '')
    <div
        {{ $attributes->merge([
            'class' => 'mb-6 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm '.$estilo['clases']
        ]) }}
        role="{{ $estilo['rol'] }}"
        @if ($estilo['rol'] === 'alert') aria-live="assertive"
        @else aria-live="polite" @endif
    >
        <span
            aria-hidden="true"
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-current font-bold"
        >{{ $estilo['icono'] }}</span>

        <div class="min-w-0 flex-1">
            <span class="sr-only">{{ $estilo['etiqueta'] }}: </span>

            @if (filled($mensaje))
                {{ $mensaje }}
            @else
                {{ $slot }}
            @endif
        </div>

        @if ($dismissible)
            <button
                type="button"
                onclick="this.closest('[role=status], [role=alert]')?.remove()"
                class="shrink-0 rounded text-current opacity-75 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current"
                aria-label="Cerrar notificación"
            >
                <span aria-hidden="true" class="text-lg leading-none font-bold">&times;</span>
            </button>
        @endif
    </div>
@endif
