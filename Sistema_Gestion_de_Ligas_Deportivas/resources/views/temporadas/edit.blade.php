@extends('layouts.admin')

@section('title', 'Editar Temporada - SGLD')
@section('header', 'Editar Temporada Deportiva')

@section('content')
<div class="mx-auto w-full max-w-3xl rounded-xl border border-gray-700 bg-gray-800 p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white sm:text-2xl">
            Modificar datos de la temporada
        </h2>
        <p class="mt-2 text-sm text-gray-400">
            Actualiza el nombre y las fechas de la temporada seleccionada.
        </p>
    </div>

    @if (session('success'))
        <div role="status" class="mb-5 rounded-lg border border-green-500/40 bg-green-500/10 p-4 text-sm text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-semibold">No se pudieron guardar los cambios.</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('temporadas.update', $temporada) }}"
          method="POST" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="nombre" class="mb-1 block text-sm font-medium text-gray-200">
                Nombre de la temporada <span class="text-red-400">*</span>
            </label>
            <input type="text" name="nombre" id="nombre"
                   value="{{ old('nombre', $temporada->nombre) }}"
                   maxlength="255" required
                   @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror
                   class="w-full rounded-lg border border-gray-600 bg-gray-900 px-4 py-3 text-white focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/40">
            @error('nombre')
                <p id="nombre-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="fecha_inicio" class="mb-1 block text-sm font-medium text-gray-200">
                    Fecha de inicio <span class="text-red-400">*</span>
                </label>
                <input type="date" name="fecha_inicio" id="fecha_inicio"
                       value="{{ old('fecha_inicio', $temporada->fecha_inicio) }}"
                       required
                       @error('fecha_inicio') aria-invalid="true" aria-describedby="fecha-inicio-error" @enderror
                       class="w-full min-w-0 rounded-lg border border-gray-600 bg-gray-900 px-3 py-3 text-white focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/40">
                @error('fecha_inicio')
                    <p id="fecha-inicio-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="fecha_fin" class="mb-1 block text-sm font-medium text-gray-200">
                    Fecha de fin <span class="text-red-400">*</span>
                </label>
                <input type="date" name="fecha_fin" id="fecha_fin"
                       value="{{ old('fecha_fin', $temporada->fecha_fin) }}"
                       required
                       @error('fecha_fin') aria-invalid="true" aria-describedby="fecha-fin-error" @enderror
                       class="w-full min-w-0 rounded-lg border border-gray-600 bg-gray-900 px-3 py-3 text-white focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/40">
                @error('fecha_fin')
                    <p id="fecha-fin-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-gray-700 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('temporadas.index') }}"
               class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-600 px-5 py-3 text-sm font-medium text-gray-200 transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 focus:ring-offset-gray-800">
                Cancelar
            </a>
            <button type="submit"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-green-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 focus:ring-offset-gray-800">
                Guardar cambios
            </button>
        </div>
    </form>
</div>
@endsection
