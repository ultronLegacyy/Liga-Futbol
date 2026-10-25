@props([
    'id',
    'title',
    'subtitle' => null,
    'maxWidth' => 'lg',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        default => 'max-w-lg',
    };
@endphp

<div id="{{ $id }}"
     role="dialog"
     aria-modal="true"
     aria-labelledby="{{ $id }}-title"
     class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/75 backdrop-blur-sm transition-opacity duration-200">
    <div class="flex min-h-screen items-center justify-center p-4 text-center">
        <div class="relative w-full {{ $maxWidthClass }} transform overflow-hidden rounded-xl bg-gray-800 border border-gray-700 text-left shadow-2xl transition-all">
            <!-- Encabezado del Modal -->
            <div class="flex items-start justify-between border-b border-gray-700 p-5">
                <div>
                    <h3 id="{{ $id }}-title" class="text-lg font-bold text-white">
                        {{ $title }}
                    </h3>
                    @if($subtitle)
                        <p class="mt-1 text-xs text-gray-400">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button"
                        onclick="cerrarModal('{{ $id }}')"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-700 hover:text-white transition"
                        aria-label="Cerrar modal">
                    <span class="text-xl leading-none font-bold">&times;</span>
                </button>
            </div>

            <!-- Contenido del Modal -->
            <div class="p-6">
                {{ $slot }}
            </div>

            @if(isset($footer))
                <!-- Pie del Modal -->
                <div class="flex items-center justify-end gap-3 border-t border-gray-700 bg-gray-900/60 px-6 py-4">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
