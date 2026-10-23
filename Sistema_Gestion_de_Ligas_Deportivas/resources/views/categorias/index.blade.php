@extends('layouts.admin')

@section('title', 'Categorías de '.$temporada->nombre.' - SGLD')
@section('header', 'Categorías y Grupos')

@section('content')
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Categorías de {{ $temporada->nombre }}</h2>
            <p class="text-sm text-gray-400">Categorías por rama con los grupos en que se dividen.</p>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('temporadas.show', $temporada) }}" class="text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
                Volver a la temporada
            </a>
            @can('create', [App\Models\Categoria::class, $temporada])
                <a href="{{ route('temporadas.categorias.create', $temporada) }}" class="px-4 py-2 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                    + Nueva categoría
                </a>
            @endcan
        </div>
    </div>

    @include('categorias._cerrada')
    @include('categorias._exito')

    @forelse ($categorias->groupBy(fn ($categoria) => $categoria->rama ?? 'Sin rama') as $rama => $categoriasDeLaRama)
        <section class="mb-6" aria-labelledby="rama-{{ Str::slug($rama) }}">
            <h3 id="rama-{{ Str::slug($rama) }}" class="text-lg font-semibold text-white mb-2">Rama {{ $rama }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <caption class="sr-only">Categorías de la rama {{ $rama }}</caption>
                    <thead class="bg-gray-700/50 text-gray-200 uppercase text-xs">
                        <tr>
                            <th scope="col" class="px-4 py-3">Categoría</th>
                            <th scope="col" class="px-4 py-3">Años de nacimiento</th>
                            <th scope="col" class="px-4 py-3">Edad mínima</th>
                            <th scope="col" class="px-4 py-3">Grupos</th>
                            <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach ($categoriasDeLaRama as $categoria)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-white">{{ $categoria->nombre }}</td>
                                <td class="px-4 py-3">@include('categorias._rango')</td>
                                <td class="px-4 py-3">{{ $categoria->edad_min !== null ? $categoria->edad_min.' años' : 'Sin edad mínima' }}</td>
                                <td class="px-4 py-3">
                                    {{ $categoria->grupos->isNotEmpty() ? $categoria->grupos->pluck('nombre')->join(', ') : 'Sin grupos' }}
                                </td>
                                <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('categorias.show', $categoria) }}" class="text-blue-400 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">
                                        Ver<span class="sr-only"> {{ $categoria->nombre }}</span>
                                    </a>
                                    @can('update', $categoria)
                                        <a href="{{ route('categorias.edit', $categoria) }}" class="text-yellow-400 hover:underline focus:outline-none focus:ring-2 focus:ring-yellow-400 rounded">
                                            Editar<span class="sr-only"> {{ $categoria->nombre }}</span>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <p class="px-4 py-6 text-center text-gray-400 bg-gray-900/50 border border-gray-700 rounded-lg">
            Esta temporada no tiene categorías registradas.
        </p>
    @endforelse
</div>
@endsection
