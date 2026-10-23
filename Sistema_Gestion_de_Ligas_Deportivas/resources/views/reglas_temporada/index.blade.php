@extends('layouts.admin')

@section('title', 'Reglas de temporada - SGLD')
@section('header', 'Reglas de la Temporada')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-6 max-w-5xl">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">{{ $temporada->nombre }}</h2>
            <p class="text-sm text-gray-400">Historial de reglas versionadas</p>
        </div>
        <a href="{{ route('temporadas.show', $temporada) }}" class="text-gray-400 hover:text-white">
            Volver a la temporada
        </a>
    </div>

    @if (session('success'))
        <p class="mb-4 text-green-400">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-400">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-300">
            <thead class="bg-gray-700 text-white">
                <tr>
                    <th class="p-3">Versión</th>
                    <th class="p-3">Reglas</th>
                    <th class="p-3">Motivo</th>
                    <th class="p-3">Usuario</th>
                    <th class="p-3">Fecha</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reglas as $regla)
                    <tr class="border-t border-gray-700 align-top">
                        <td class="p-3">v{{ $regla->version }}</td>
                        <td class="p-3">
                            <pre class="whitespace-pre-wrap">{{ json_encode($regla->reglas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </td>
                        <td class="p-3">{{ $regla->motivo }}</td>
                        <td class="p-3">{{ $regla->usuario?->name ?? 'No disponible' }}</td>
                        <td class="p-3">{{ $regla->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-4 text-center">No hay reglas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('reglas_temporada.editar')
        @if ($temporada->estado !== 'cerrada')
            <div class="border-t border-gray-700 mt-6 pt-6">
                <h3 class="text-lg font-semibold text-white mb-4">Registrar nuevas reglas</h3>

                <form method="POST" action="{{ route('temporadas.reglas.store', $temporada) }}">
                    @csrf

                    <label for="reglas_json" class="block text-sm text-gray-300 mb-2">Reglas en formato JSON</label>
                    {{-- Se parte de la versión vigente; sin versiones, de un ejemplo con las cinco categorías obligatorias --}}
                    @php
                        $reglasIniciales = $reglas->first()?->reglas ?? [
                            'cuotas' => ['inscripcion' => 0],
                            'fechas_limite' => ['inscripcion' => now()->format('Y-m-d')],
                            'clasificacion' => ['puntos_victoria' => 3, 'puntos_empate' => 1, 'puntos_derrota' => 0],
                            'desempates' => ['diferencia_goles', 'goles_favor'],
                            'ascenso_descenso' => ['equipos_ascienden' => 0, 'equipos_descienden' => 0],
                        ];
                    @endphp
                    <textarea id="reglas_json" name="reglas_json" rows="16" required class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-3 font-mono text-sm">{{ old('reglas_json', json_encode($reglasIniciales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>

                    <label for="motivo" class="block text-sm text-gray-300 mt-4 mb-2">Motivo del cambio</label>
                    <textarea id="motivo" name="motivo" maxlength="1000" required class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-3">{{ old('motivo') }}</textarea>

                    <button type="submit" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Guardar nueva versión
                    </button>
                </form>
            </div>
        @else
            <p class="mt-6 text-amber-300">La temporada está cerrada. Solo se permite consultar sus reglas.</p>
        @endif
    @endcan
</div>
@endsection