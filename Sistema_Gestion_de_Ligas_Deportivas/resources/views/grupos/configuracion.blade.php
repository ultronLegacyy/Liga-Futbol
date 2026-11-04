@extends('layouts.admin')

@section('title', 'Formato de '.$grupo->nombre.' - SGLD')
@section('header', 'Categorías y Grupos')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
    $criterios = $grupo->criterios_clasificacion ?? [];
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl space-y-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Formato y clasificación · {{ $grupo->nombre }}</h2>
            <p class="text-sm text-gray-400">{{ $grupo->categoria->nombre }} · {{ $grupo->temporada->nombre }}</p>
        </div>
        <a href="{{ route('categorias.show', $grupo->categoria) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Volver a la categoría
        </a>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    {{-- Valores vigentes del grupo (CA2 y CA3) --}}
    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
        <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
            <dt class="text-gray-400">Formato de competencia</dt>
            <dd class="mt-1 text-white">{{ App\Models\Grupo::FORMATOS[$grupo->formato_competencia] ?? 'Sin configurar' }}</dd>
        </div>
        @foreach (App\Models\Grupo::CRITERIOS as $clave => $etiqueta)
            <div class="bg-gray-900/50 border border-gray-700 rounded-lg p-4">
                <dt class="text-gray-400">{{ $etiqueta }}</dt>
                <dd class="mt-1 text-white">{{ $criterios[$clave] ?? 'Sin configurar' }}</dd>
            </div>
        @endforeach
    </dl>

    <p class="text-xs text-gray-400">
        Los puntos por resultado y los desempates se configuran en las reglas de la temporada.
    </p>

    @if ($grupo->estaCerrado())
        <x-alert type="info" message="La temporada está cerrada: el formato y los criterios solo se pueden consultar." />
    @else
        @can('update', $grupo)
            @if ($errors->any())
                <x-alert type="error" message="No se guardó la configuración. Revisa los campos marcados." />
            @endif

            <form method="POST" action="{{ route('grupos.configuracion.update', $grupo) }}" novalidate class="space-y-5 border-t border-gray-700 pt-6">
                @csrf
                @method('PUT')

                <x-form.field name="formato_competencia" label="Formato de competencia" required :error="$errors->first('formato_competencia')">
                    <select
                        id="formato_competencia"
                        name="formato_competencia"
                        required
                        class="{{ $campo }} {{ $errors->has('formato_competencia') ? 'border-red-500' : 'border-gray-600' }}"
                        @if ($errors->has('formato_competencia')) aria-invalid="true" aria-describedby="formato_competencia-error" @endif
                    >
                        <option value="">Selecciona un formato</option>
                        @foreach (App\Models\Grupo::FORMATOS as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('formato_competencia', $grupo->formato_competencia) === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                @foreach (App\Models\Grupo::CRITERIOS as $clave => $etiqueta)
                    @php($error = $errors->first('criterios_clasificacion.'.$clave))
                    <x-form.field :name="'criterio_'.$clave" :label="$etiqueta" required :error="$error">
                        <input
                            type="number"
                            id="criterio_{{ $clave }}"
                            name="criterios_clasificacion[{{ $clave }}]"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            required
                            value="{{ old('criterios_clasificacion.'.$clave, $criterios[$clave] ?? '') }}"
                            class="{{ $campo }} sm:max-w-xs {{ $error ? 'border-red-500' : 'border-gray-600' }}"
                            @if ($error) aria-invalid="true" aria-describedby="criterio_{{ $clave }}-error" @endif
                        >
                    </x-form.field>
                @endforeach

                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                    Guardar configuración
                </button>
            </form>
        @endcan
    @endif
</div>
@endsection
