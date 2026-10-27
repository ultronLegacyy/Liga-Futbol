
@extends('layouts.admin')

@section('title', 'Detalle de Temporada - SGLD')
@section('header', 'Detalles de la Temporada')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-6 max-w-5xl">

    {{-- Encabezado --}}
    <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">
                {{ $temporada->nombre ?? 'Detalle de Temporada' }}
            </h2>

            <p class="text-sm text-gray-400 mt-1">
                Información general y configuración de la temporada.
            </p>
        </div>

        <a
            href="{{ route('temporadas.index') }}"
            class="text-sm text-gray-400 hover:text-white transition"
        >
            ← Volver al listado
        </a>
    </div>

    {{-- Estado de la temporada --}}
    <div class="mb-6">
        @if ($temporada->estado === 'cerrada')
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-900/40 text-red-300 border border-red-700">
                Temporada cerrada
            </span>

            <p class="text-sm text-gray-400 mt-2">
                Esta temporada está cerrada. Sus reglas y su estructura ya no se pueden modificar.
                Puedes consultar sus registros históricos.
            </p>
        @elseif ($temporada->estado === 'activa')
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-900/40 text-green-300 border border-green-700">
                Activa
            </span>
        @elseif ($temporada->estado === 'borrador')
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-700 text-gray-300 border border-gray-600">
                Borrador
            </span>
        @else
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-700 text-gray-300 border border-gray-600">
                {{ ucfirst($temporada->estado ?? 'Estado no especificado') }}
            </span>
        @endif
    </div>

    {{-- Información general --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
            <span class="text-xs text-gray-400 uppercase tracking-wide">
                Fecha de inicio
            </span>

            <p class="text-lg font-semibold text-white mt-1">
                {{ $temporada->fecha_inicio ? \Illuminate\Support\Carbon::parse($temporada->fecha_inicio)->format('d/m/Y') : 'N/A' }}
            </p>
        </div>

        <div class="bg-gray-900/50 p-4 rounded-lg border border-gray-700">
            <span class="text-xs text-gray-400 uppercase tracking-wide">
                Fecha de finalización
            </span>

            <p class="text-lg font-semibold text-white mt-1">
                {{ $temporada->fecha_fin ? \Illuminate\Support\Carbon::parse($temporada->fecha_fin)->format('d/m/Y') : 'N/A' }}
            </p>
        </div>
    </div>

    {{-- Historial de convocatorias --}}
    <section class="border-t border-gray-700 pt-6">
        <h3 class="text-lg font-semibold text-white mb-2">
            Convocatoria y versionado
        </h3>

        <p class="text-sm text-gray-400 mb-4">
            Consulta las versiones de la convocatoria, los motivos de sus cambios
            y los usuarios que las registraron.
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-700">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3">Versión</th>
                        <th class="px-4 py-3">Motivo</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Documento</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-700">
                    @forelse ($convocatorias as $convocatoria)
                        @php
                            $auditoria = $altas->get($convocatoria->id);
                        @endphp

                        <tr class="hover:bg-gray-700/30">
                            <td class="px-4 py-3 font-semibold text-white">
                                v{{ $convocatoria->version }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $auditoria?->motivo ?? 'Sin motivo registrado' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $auditoria?->usuario?->name ?? 'Usuario no disponible' }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $auditoria?->created_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                            </td>

                            <td class="px-4 py-3">
                                <a
                                    href="{{ route('temporadas.convocatoria', [
                                        'temporada' => $temporada,
                                        'version' => $convocatoria->version,
                                    ]) }}"
                                    class="text-blue-400 hover:text-blue-300 hover:underline"
                                >
                                    Consultar PDF
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-400">
                                No hay convocatorias registradas para esta temporada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Descargar convocatoria vigente --}}
        <div class="mt-6 flex flex-wrap gap-3">
            <a
                href="{{ route('temporadas.convocatoria', $temporada) }}"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition"
            >
                Descargar convocatoria vigente
            </a>
        </div>
    </section>

    {{-- Accesos de gestión --}}
    <section class="border-t border-gray-700 mt-6 pt-6">
        <h3 class="text-lg font-semibold text-white mb-2">
            Gestión de la temporada
        </h3>

        <p class="text-sm text-gray-400 mb-4">
            Accede a las categorías, los grupos y el historial de reglas
            registrado para esta temporada.
        </p>

        <div class="flex flex-wrap gap-3">
            @can('viewAny', App\Models\Categoria::class)
                <a
                    href="{{ route('temporadas.categorias.index', $temporada) }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-600 transition"
                >
                    Categorías y grupos
                </a>
            @endcan

            @can('reglas_temporada.ver')
                <a
                    href="{{ route('temporadas.reglas.index', $temporada) }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition"
                >
                    Consultar reglas e historial
                </a>
            @endcan
        </div>

        {{-- Aviso de consulta histórica --}}
        @if ($temporada->estado === 'cerrada' && auth()->user()?->can('reglas_temporada.ver'))
            <div class="mt-4 rounded-lg border border-amber-700/60 bg-amber-900/20 p-4">
                <h4 class="text-sm font-semibold text-amber-300">
                    Consulta histórica en modo de solo lectura
                </h4>

                <p class="text-sm text-gray-300 mt-1">
                    Puedes consultar las versiones de las reglas que se aplicaron.
                    Las reglas y la estructura de esta temporada ya no se pueden modificar.
                </p>
            </div>
        @endif
    </section>

</div>
@endsection
