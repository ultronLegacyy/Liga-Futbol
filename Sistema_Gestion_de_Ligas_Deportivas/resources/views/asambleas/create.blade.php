@extends('layouts.admin')

@section('title', 'Convocar asamblea')

@section('content')
<main class="mx-auto max-w-3xl p-6">
    <div class="mb-6">
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">HU-053 · Convocatoria</p>
        <h1 class="mt-2 text-3xl font-bold text-white">Convocar asamblea de delegados</h1>
    </div>

    <form method="POST" action="{{ route('asambleas.store') }}" class="space-y-5 rounded-xl border border-gray-700 bg-gray-800 p-6">
        @csrf
        <div class="grid gap-5 md:grid-cols-3">
            <div>
                <label for="tipo" class="mb-1 block text-sm font-medium text-gray-200">Tipo</label>
                <select id="tipo" name="tipo" required class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                    @foreach (App\Models\Asamblea::TIPOS as $tipo)
                        <option value="{{ $tipo }}" @selected(old('tipo', 'Ordinaria') === $tipo)>{{ $tipo }}</option>
                    @endforeach
                </select>
                @error('tipo') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="fecha" class="mb-1 block text-sm font-medium text-gray-200">Fecha</label>
                <input id="fecha" name="fecha" type="date" required min="{{ App\Http\Requests\StoreAsambleaRequest::hoy() }}" value="{{ old('fecha') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                @error('fecha') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="hora" class="mb-1 block text-sm font-medium text-gray-200">Hora</label>
                <input id="hora" name="hora" type="time" required value="{{ old('hora') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                @error('hora') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="lugar" class="mb-1 block text-sm font-medium text-gray-200">Lugar</label>
            <input id="lugar" name="lugar" required maxlength="200" value="{{ old('lugar') }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
            @error('lugar') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="temporada_id" class="mb-1 block text-sm font-medium text-gray-200">Temporada (opcional)</label>
            <select id="temporada_id" name="temporada_id" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">
                <option value="">Sin temporada</option>
                @foreach ($temporadas as $temporada)
                    <option value="{{ $temporada->id }}" @selected(old('temporada_id') == $temporada->id)>{{ $temporada->nombre }}</option>
                @endforeach
            </select>
            @error('temporada_id') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="orden_del_dia" class="mb-1 block text-sm font-medium text-gray-200">Orden del día</label>
            <textarea id="orden_del_dia" name="orden_del_dia" rows="6" required maxlength="5000" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-3 py-2 text-white">{{ old('orden_del_dia') }}</textarea>
            @error('orden_del_dia') <p class="mt-1 text-sm text-red-300">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-3 border-t border-gray-700 pt-5">
            <a href="{{ route('asambleas.index') }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 hover:bg-gray-700">Cancelar</a>
            <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-500">Convocar</button>
        </div>
    </form>
</main>
@endsection
