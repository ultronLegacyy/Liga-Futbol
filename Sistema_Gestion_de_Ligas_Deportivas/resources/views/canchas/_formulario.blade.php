
{{-- Formulario compartido de alta y edición de canchas (IU-03) --}}
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

@if ($errors->any())
    <x-alert type="error" message="No se guardó la cancha. Revisa los campos marcados." />
@endif

<form method="POST" action="{{ $accion }}" novalidate class="space-y-5">
    @csrf

    @if ($metodo === 'PUT')
        @method('PUT')
    @endif

    <x-form.field
        name="nombre"
        label="Nombre"
        required
        :error="$errors->first('nombre')"
    >
        <input
            type="text"
            id="nombre"
            name="nombre"
            maxlength="255"
            required
            autofocus
            value="{{ old('nombre', $cancha->nombre) }}"
            class="{{ $campo }} {{ $errors->has('nombre') ? 'border-red-500' : 'border-gray-600' }}"
            @if ($errors->has('nombre')) aria-invalid="true" aria-describedby="nombre-error" @endif
        >
    </x-form.field>

    <x-form.field
        name="direccion_ubicacion"
        label="Dirección o ubicación"
        help="Campo opcional."
        :error="$errors->first('direccion_ubicacion')"
    >
        <input
            type="text"
            id="direccion_ubicacion"
            name="direccion_ubicacion"
            maxlength="255"
            value="{{ old('direccion_ubicacion', $cancha->direccion_ubicacion) }}"
            class="{{ $campo }} {{ $errors->has('direccion_ubicacion') ? 'border-red-500' : 'border-gray-600' }}"
            @if ($errors->has('direccion_ubicacion')) aria-invalid="true" aria-describedby="direccion_ubicacion-error" @endif
        >
    </x-form.field>

    <x-form.field
        name="capacidad_partidos_consecutivos"
        label="Capacidad de partidos consecutivos"
        required
        help="Cuántos partidos seguidos se pueden jugar en la cancha. Mínimo 1."
        :error="$errors->first('capacidad_partidos_consecutivos')"
    >
        <input
            type="number"
            id="capacidad_partidos_consecutivos"
            name="capacidad_partidos_consecutivos"
            min="1"
            step="1"
            inputmode="numeric"
            required
            value="{{ old('capacidad_partidos_consecutivos', $cancha->capacidad_partidos_consecutivos) }}"
            class="{{ $campo }} sm:max-w-xs {{ $errors->has('capacidad_partidos_consecutivos') ? 'border-red-500' : 'border-gray-600' }}"
            aria-describedby="capacidad_partidos_consecutivos-ayuda @if ($errors->has('capacidad_partidos_consecutivos')) capacidad_partidos_consecutivos-error @endif"
            @if ($errors->has('capacidad_partidos_consecutivos')) aria-invalid="true" @endif
        >
    </x-form.field>

    <p class="text-xs text-gray-400">
        <span class="text-red-400" aria-hidden="true">*</span> Campo obligatorio.
    </p>

    <div class="flex flex-wrap gap-3">
        <button
            type="submit"
            class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition"
        >
            {{ $textoBoton }}
        </button>

        <a
            href="{{ $cancelar }}"
            class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400"
        >
            Cancelar
        </a>
    </div>
</form>
