{{-- Selección de rol y temporada, compartida por el alta y la asignación de roles (HU-013) --}}
<x-form.field name="rol" label="Rol" required :error="$errors->first('rol')">
    <select
        id="rol"
        name="rol"
        required
        class="{{ $campo }} {{ $errors->has('rol') ? 'border-red-500' : 'border-gray-600' }}"
        @if ($errors->has('rol')) aria-invalid="true" aria-describedby="rol-error" @endif
    >
        <option value="">Selecciona un rol</option>
        @foreach ($roles as $rol)
            <option value="{{ $rol }}" @selected(old('rol') === $rol)>{{ App\Models\PersonaRol::ETIQUETAS[$rol] }}</option>
        @endforeach
    </select>
</x-form.field>

<x-form.field
    name="temporada_id"
    label="Temporada"
    required
    help="Solo aparecen las temporadas que no están cerradas."
    :error="$errors->first('temporada_id')"
>
    <select
        id="temporada_id"
        name="temporada_id"
        required
        class="{{ $campo }} {{ $errors->has('temporada_id') ? 'border-red-500' : 'border-gray-600' }}"
        aria-describedby="temporada_id-ayuda @if ($errors->has('temporada_id')) temporada_id-error @endif"
        @if ($errors->has('temporada_id')) aria-invalid="true" @endif
    >
        <option value="">Selecciona una temporada</option>
        @foreach ($temporadas as $temporada)
            <option value="{{ $temporada->id }}" @selected((string) old('temporada_id') === (string) $temporada->id)>{{ $temporada->nombre }}</option>
        @endforeach
    </select>
</x-form.field>
