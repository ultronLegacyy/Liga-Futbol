
@props([
    'name',
    'label',
    'required' => false,
    'help' => null,
    'error' => null,
])

@php
    $mensajeError = $error;
    $fieldId = $attributes->get('id', $name);

    $descripciones = [];

    if (filled($help)) {
        $descripciones[] = $fieldId.'-ayuda';
    }

    if (filled($mensajeError)) {
        $descripciones[] = $fieldId.'-error';
    }
@endphp

<div class="space-y-1">
    <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-200">
        {{ $label }}

        @if ($required)
            <span class="text-red-400" aria-hidden="true">*</span>
            <span class="sr-only">(obligatorio)</span>
        @endif
    </label>

    <div>
        {{ $slot }}
    </div>

    @if (filled($help))
        <p id="{{ $fieldId }}-ayuda" class="text-xs text-gray-400">
            {{ $help }}
        </p>
    @endif

    @if (filled($mensajeError))
        <p
            id="{{ $fieldId }}-error"
            role="alert"
            class="text-sm text-red-400"
        >
            {{ $mensajeError }}
        </p>
    @endif
</div>
