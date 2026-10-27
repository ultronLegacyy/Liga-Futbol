@extends('layouts.admin')

@section('title', 'Crear Temporada - SGLD')
@section('header', 'Crear Temporada Deportiva')

@section('content')
<div class="mx-auto w-full max-w-3xl rounded-xl border border-gray-700 bg-gray-800 p-4 sm:p-6 lg:p-8">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-white sm:text-2xl">
            Registrar nueva temporada
        </h2>
        <p class="mt-2 text-sm text-gray-400">
            Completa los datos y adjunta la convocatoria oficial en formato PDF.
            Los campos marcados con <span class="text-red-400">*</span> son obligatorios.
        </p>
    </div>

    @if (session('success'))
        <div role="status" class="mb-5 rounded-lg border border-green-500/40 bg-green-500/10 p-4 text-sm text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-semibold">No se pudo registrar la temporada.</p>
            <p class="mt-1">Revisa los siguientes campos:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('temporadas.store') }}" method="POST"
          enctype="multipart/form-data" class="space-y-5">
        @csrf

        <div>
            <label for="nombre" class="mb-1 block text-sm font-medium text-gray-200">
                Nombre de la temporada <span class="text-red-400">*</span>
            </label>
            <input type="text" id="nombre" name="nombre"
                   value="{{ old('nombre') }}" maxlength="255" required
                   autocomplete="off"
                   @error('nombre') aria-invalid="true" aria-describedby="nombre-error" @enderror
                   class="w-full rounded-lg border border-gray-600 bg-gray-900 px-4 py-3 text-white placeholder-gray-500 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/40"
                   placeholder="Ej. Torneo Apertura 2027">
            @error('nombre')
                <p id="nombre-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="fecha_inicio" class="mb-1 block text-sm font-medium text-gray-200">
                    Fecha de inicio <span class="text-red-400">*</span>
                </label>
                <input type="date" id="fecha_inicio" name="fecha_inicio"
                       value="{{ old('fecha_inicio') }}" required
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
                <input type="date" id="fecha_fin" name="fecha_fin"
                       value="{{ old('fecha_fin') }}" required
                       @error('fecha_fin') aria-invalid="true" aria-describedby="fecha-fin-error" @enderror
                       class="w-full min-w-0 rounded-lg border border-gray-600 bg-gray-900 px-3 py-3 text-white focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/40">
                @error('fecha_fin')
                    <p id="fecha-fin-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="convocatoria_pdf" class="mb-1 block text-sm font-medium text-gray-200">
                Convocatoria oficial (PDF) <span class="text-red-400">*</span>
            </label>
            <input type="file" id="convocatoria_pdf" name="convocatoria_pdf"
                   accept=".pdf,application/pdf" required
                   @error('convocatoria_pdf') aria-invalid="true" aria-describedby="convocatoria-error" @enderror
                   class="block w-full min-w-0 rounded-lg border border-gray-600 bg-gray-900 text-sm text-gray-300 file:mr-4 file:border-0 file:bg-gray-700 file:px-4 file:py-3 file:font-medium file:text-white hover:file:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-green-500">
            <p class="mt-1 text-xs text-gray-400">Formato PDF. Tamaño máximo: 5 MB.</p>
            @error('convocatoria_pdf')
                <p id="convocatoria-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="motivo" class="mb-1 block text-sm font-medium text-gray-200">
                Motivo del registro <span class="text-red-400">*</span>
            </label>
            <textarea id="motivo" name="motivo" rows="4" maxlength="1000" required
                      aria-describedby="motivo-ayuda @error('motivo') motivo-error @enderror"
                      @error('motivo') aria-invalid="true" @enderror
                      class="w-full resize-y rounded-lg border border-gray-600 bg-gray-900 px-4 py-3 text-white placeholder-gray-500 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/40"
                      placeholder="Describe por qué se registra esta temporada...">{{ old('motivo') }}</textarea>
            <p id="motivo-ayuda" class="mt-1 text-xs text-gray-400">
                Este motivo se conservará en el registro de auditoría. Máximo 1000 caracteres.
            </p>
            @error('motivo')
                <p id="motivo-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-gray-700 pt-5 sm:flex-row sm:justify-end">
            <a href="{{ route('temporadas.index') }}"
               class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-600 px-5 py-3 text-sm font-medium text-gray-200 transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 focus:ring-offset-gray-800">
                Cancelar
            </a>
            <button type="submit"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-green-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 focus:ring-offset-gray-800">
                Guardar temporada
            </button>
        </div>
    </form>
</div>
@endsection
