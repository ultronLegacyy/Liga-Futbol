{{-- Formulario compartido del alta y la edición de una categoría: cada campo muestra su error en español (IU-03) --}}
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
    $borde = fn (string $nombre): string => $errors->has($nombre) ? 'border-red-500' : 'border-gray-600';
@endphp

@if ($errors->any())
    <div role="alert" class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
        No se guardó la categoría. Revisa los campos marcados.
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
            value="{{ old('nombre', $categoria->nombre) }}" placeholder="Por ejemplo, Primera Fuerza"
            class="{{ $campo }} {{ $borde('nombre') }}"
            @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror>
        @error('nombre')
            <p id="nombre-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="rama" class="block text-sm font-medium text-gray-200 mb-1">
            Rama <span class="text-red-400" aria-hidden="true">*</span>
        </label>
        <select id="rama" name="rama" required
            class="{{ $campo }} sm:max-w-xs {{ $borde('rama') }}"
            @error('rama') aria-invalid="true" aria-describedby="rama-error" @enderror>
            <option value="">Selecciona una rama</option>
            @foreach (App\Models\Categoria::RAMAS as $rama)
                <option value="{{ $rama }}" @selected(old('rama', $categoria->rama) === $rama)>{{ $rama }}</option>
            @endforeach
        </select>
        @error('rama')
            <p id="rama-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <fieldset>
        <legend class="block text-sm font-medium text-gray-200 mb-1">
            Años de nacimiento <span class="text-gray-400">(opcional)</span>
        </legend>
        <p id="anios-ayuda" class="mb-2 text-xs text-gray-400">Año más antiguo y más reciente que admite la categoría. Puedes capturar solo uno.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:max-w-md">
            @foreach (['anio_nacimiento_desde' => 'Desde', 'anio_nacimiento_hasta' => 'Hasta'] as $nombre => $etiqueta)
                <div>
                    <label for="{{ $nombre }}" class="block text-sm text-gray-300 mb-1">{{ $etiqueta }}</label>
                    <input type="number" id="{{ $nombre }}" name="{{ $nombre }}"
                        min="1900" max="{{ now()->year }}" step="1" inputmode="numeric"
                        value="{{ old($nombre, $categoria->{$nombre}) }}"
                        class="{{ $campo }} {{ $borde($nombre) }}"
                        aria-describedby="anios-ayuda @error($nombre) {{ $nombre }}-error @enderror"
                        @error($nombre) aria-invalid="true" @enderror>
                    @error($nombre)
                        <p id="{{ $nombre }}-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
    </fieldset>

    <div>
        <label for="edad_min" class="block text-sm font-medium text-gray-200 mb-1">
            Edad mínima <span class="text-gray-400">(opcional)</span>
        </label>
        <input type="number" id="edad_min" name="edad_min" min="0" max="99" step="1" inputmode="numeric"
            value="{{ old('edad_min', $categoria->edad_min) }}"
            class="{{ $campo }} sm:max-w-xs {{ $borde('edad_min') }}"
            @error('edad_min') aria-invalid="true" aria-describedby="edad_min-error" @enderror>
        @error('edad_min')
            <p id="edad_min-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
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
