@props([
    'type' => 'info', // success, error, warning, info
    'message' => null,
    'dismissible' => true,
])

@php
    $styles = match ($type) {
        'success' => 'bg-emerald-950/60 border-emerald-600/40 text-emerald-200',
        'error', 'danger' => 'bg-red-950/60 border-red-600/40 text-red-200',
        'warning' => 'bg-amber-950/60 border-amber-600/40 text-amber-200',
        default => 'bg-sky-950/60 border-sky-600/40 text-sky-200',
    };
    $accent = match ($type) {
        'success' => 'text-emerald-400 border-emerald-500/40',
        'error', 'danger' => 'text-red-400 border-red-500/40',
        'warning' => 'text-amber-400 border-amber-500/40',
        default => 'text-sky-400 border-sky-500/40',
    };
    $etiqueta = match ($type) {
        'success' => 'Exito',
        'error', 'danger' => 'Error',
        'warning' => 'Atencion',
        default => 'Informacion',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border p-4 text-sm {$styles} transition-all duration-150"]) }} role="alert">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-3 flex-1">
            <span class="font-bold uppercase tracking-wider text-xs px-2 py-0.5 rounded border {{ $accent }}">
                {{ $etiqueta }}
            </span>
            <div class="leading-relaxed">
                {{ $message ?? $slot }}
            </div>
        </div>
        @if($dismissible)
            <button type="button"
                    onclick="this.closest('[role=alert]').remove()"
                    class="text-gray-400 hover:text-white transition"
                    aria-label="Cerrar notificacion">
                <span class="text-lg leading-none font-bold">&times;</span>
            </button>
        @endif
    </div>
</div>
