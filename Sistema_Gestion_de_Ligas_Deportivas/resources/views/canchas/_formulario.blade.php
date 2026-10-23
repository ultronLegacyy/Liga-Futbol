{{-- Formulario compartido del alta y la edición de una cancha: cada campo muestra su error en español (IU-03) --}}
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
@endphp

@if ($errors->any())
    <div role="alert" class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
        No se guardó la cancha. Revisa los campos marcados.
    </div>
@endif

<form method="POST" action="{{ $accion }}" novalidate class="space-y-5">
    @csrf
    @if ($metodo === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label for="nombre" class="block text-sm font-medium text-gray-200 mb-1">
            Nombre <span class="text-red-400" aria-hidden="true">*</span>
        </label>
        <input type="text" id="nombre" name="nombre" maxlength="255" required autofocus
            value="{{ old('nombre', $cancha->nombre) }}"
            class="{{ $campo }} {{ $errors->has('nombre') ? 'border-red-500' : 'border-gray-600' }}"
            @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>
        @error('nombre')
            <p id="nombre-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="direccion_ubicacion" class="block text-sm font-medium text-gray-200 mb-1">
            Dirección o ubicación <span class="text-gray-400">(opcional)</span>
        </label>
        <input type="text" id="direccion_ubicacion" name="direccion_ubicacion" maxlength="255"
            value="{{ old('direccion_ubicacion', $cancha->direccion_ubicacion) }}"
            class="{{ $campo }} {{ $errors->has('direccion_ubicacion') ? 'border-red-500' : 'border-gray-600' }}"
            @error('direccion_ubicacion') aria-invalid="true" aria-describedby="direccion_ubicacion-error" @enderror>
        @error('direccion_ubicacion')
            <p id="direccion_ubicacion-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="capacidad_partidos_consecutivos" class="block text-sm font-medium text-gray-200 mb-1">
            Capacidad de partidos consecutivos <span class="text-red-400" aria-hidden="true">*</span>
        </label>
        <input type="number" id="capacidad_partidos_consecutivos" name="capacidad_partidos_consecutivos"
            min="1" step="1" inputmode="numeric" required
            value="{{ old('capacidad_partidos_consecutivos', $cancha->capacidad_partidos_consecutivos) }}"
            class="{{ $campo }} sm:max-w-xs {{ $errors->has('capacidad_partidos_consecutivos') ? 'border-red-500' : 'border-gray-600' }}"
            aria-describedby="capacidad-ayuda @error('capacidad_partidos_consecutivos') capacidad_partidos_consecutivos-error @enderror"
            @error('capacidad_partidos_consecutivos') aria-invalid="true" @enderror>
        <p id="capacidad-ayuda" class="mt-1 text-xs text-gray-400">Cuántos partidos seguidos se pueden jugar en la cancha. Mínimo 1.</p>
        @error('capacidad_partidos_consecutivos')
            <p id="capacidad_partidos_consecutivos-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <p class="text-xs text-gray-400"><span class="text-red-400" aria-hidden="true">*</span> Campo obligatorio.</p>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
            {{ $textoBoton }}
        </button>
        <a href="{{ $cancelar }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">
            Cancelar
        </a>
    </div>
</form>
