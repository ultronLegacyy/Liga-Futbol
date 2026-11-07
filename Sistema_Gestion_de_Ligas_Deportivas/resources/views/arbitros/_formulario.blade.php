{{-- Datos del padrón compartidos por el alta y la edición de árbitros (HU-026) --}}
<x-form.field name="colegio_pertenencia" label="Colegio de árbitros" help="Campo opcional." :error="$errors->first('colegio_pertenencia')">
    <input
        type="text"
        id="colegio_pertenencia"
        name="colegio_pertenencia"
        maxlength="255"
        value="{{ old('colegio_pertenencia', $arbitro->colegio_pertenencia) }}"
        class="{{ $campo }} {{ $errors->has('colegio_pertenencia') ? 'border-red-500' : 'border-gray-600' }}"
        aria-describedby="colegio_pertenencia-ayuda @if ($errors->has('colegio_pertenencia')) colegio_pertenencia-error @endif"
        @if ($errors->has('colegio_pertenencia')) aria-invalid="true" @endif
    >
</x-form.field>

<x-form.field name="tarifa_por_partido" label="Tarifa por partido (MXN)" help="Campo opcional; si se deja vacío queda en 0.00." :error="$errors->first('tarifa_por_partido')">
    <input
        type="number"
        id="tarifa_por_partido"
        name="tarifa_por_partido"
        min="0"
        step="0.01"
        inputmode="decimal"
        value="{{ old('tarifa_por_partido', $arbitro->tarifa_por_partido) }}"
        class="{{ $campo }} sm:max-w-xs {{ $errors->has('tarifa_por_partido') ? 'border-red-500' : 'border-gray-600' }}"
        aria-describedby="tarifa_por_partido-ayuda @if ($errors->has('tarifa_por_partido')) tarifa_por_partido-error @endif"
        @if ($errors->has('tarifa_por_partido')) aria-invalid="true" @endif
    >
</x-form.field>

<x-form.field name="estado" label="Disponibilidad" required help="Un árbitro inactivo no se puede asignar a partidos." :error="$errors->first('estado')">
    <select
        id="estado"
        name="estado"
        required
        class="{{ $campo }} sm:max-w-xs {{ $errors->has('estado') ? 'border-red-500' : 'border-gray-600' }}"
        aria-describedby="estado-ayuda @if ($errors->has('estado')) estado-error @endif"
        @if ($errors->has('estado')) aria-invalid="true" @endif
    >
        @foreach (App\Models\Arbitro::ESTADOS as $estado)
            <option value="{{ $estado }}" @selected(old('estado', $arbitro->estado) === $estado)>{{ $estado }}</option>
        @endforeach
    </select>
</x-form.field>
