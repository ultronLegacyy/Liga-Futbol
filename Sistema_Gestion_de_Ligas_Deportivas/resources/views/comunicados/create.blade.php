@extends('layouts.admin')

@section('title', 'Comunicados - SGLD')
@section('header', 'Comunicados')

@php
    $campo = 'w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-2.5 focus:outline-none focus:ring-2 focus:ring-green-400';
    // Tras la revisión se muestran los datos validados; tras un error, lo que se había escrito
    $asunto = $datos['asunto'] ?? old('asunto', '');
    $mensaje = $datos['mensaje'] ?? old('mensaje', '');
    $rolesElegidos = $datos['roles'] ?? (array) old('roles', []);
    $categoriaElegida = (string) ($datos['categoria_id'] ?? old('categoria_id', ''));
    $clubesElegidos = array_map('strval', array_filter($datos['clubes'] ?? (array) old('clubes', []), 'is_scalar'));
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-white">Comunicados por correo</h2>
        <p class="text-sm text-gray-400">Redacta un comunicado y elige a quién va: uno o varios roles y, si quieres, una categoría y uno o varios clubes. Solo lo reciben las cuentas activas con correo (HU-066).</p>
    </div>

    @if (session('status'))
        <div role="status" class="rounded-lg border border-green-700 bg-green-950/40 p-4 text-sm text-green-200">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-700 bg-red-950/40 p-4 text-sm text-red-200">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($total !== null)
        {{-- Paso 2: confirmar el envío con los mismos datos ya revisados --}}
        <section class="rounded-xl border border-green-700 bg-gray-800 p-6 space-y-4" aria-labelledby="titulo-confirmar">
            <h3 id="titulo-confirmar" class="text-lg font-semibold text-white">El grupo tiene {{ $total }} {{ $total === 1 ? 'destinatario' : 'destinatarios' }}</h3>
            <p class="text-sm text-gray-300">Asunto: <span class="font-medium text-white">{{ $datos['asunto'] }}</span></p>
            <form method="POST" action="{{ route('comunicados.store') }}">
                @csrf
                <input type="hidden" name="asunto" value="{{ $datos['asunto'] }}">
                {{-- textarea oculto: conserva los saltos de línea del mensaje --}}
                <textarea name="mensaje" hidden>{{ $datos['mensaje'] }}</textarea>
                @foreach ($datos['roles'] as $rol)
                    <input type="hidden" name="roles[]" value="{{ $rol }}">
                @endforeach
                @if ($datos['categoria_id'] !== null)
                    <input type="hidden" name="categoria_id" value="{{ $datos['categoria_id'] }}">
                @endif
                @foreach ($datos['clubes'] as $clubId)
                    <input type="hidden" name="clubes[]" value="{{ $clubId }}">
                @endforeach
                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">Enviar a {{ $total }} {{ $total === 1 ? 'destinatario' : 'destinatarios' }}</button>
            </form>
            <p class="text-xs text-gray-400">Para cambiar algo, corrige el formulario de abajo y vuelve a revisar.</p>
        </section>
    @endif

    {{-- Paso 1: redactar y elegir el grupo --}}
    <form method="POST" action="{{ route('comunicados.revisar') }}" class="rounded-xl border border-gray-700 bg-gray-800 p-6 space-y-5">
        @csrf
        <div>
            <label for="asunto" class="block text-sm text-gray-300 font-medium mb-1">Asunto</label>
            <input id="asunto" name="asunto" type="text" required minlength="3" maxlength="200" value="{{ $asunto }}" class="{{ $campo }}">
        </div>

        <div>
            <label for="mensaje" class="block text-sm text-gray-300 font-medium mb-1">Mensaje</label>
            <textarea id="mensaje" name="mensaje" rows="8" required minlength="5" maxlength="10000" class="{{ $campo }}">{{ $mensaje }}</textarea>
        </div>

        <fieldset>
            <legend class="block text-sm text-gray-300 font-medium mb-2">Roles destinatarios</legend>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($roles as $rol)
                    <label class="flex items-center gap-2 text-sm text-gray-200">
                        <input type="checkbox" name="roles[]" value="{{ $rol }}" @checked(in_array($rol, $rolesElegidos, true)) class="rounded border-gray-600 bg-gray-900 text-green-600 focus:ring-green-400">
                        {{ $rol }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="categoria_id" class="block text-sm text-gray-300 font-medium mb-1">Categoría (opcional)</label>
                <select id="categoria_id" name="categoria_id" class="{{ $campo }}">
                    <option value="">Todas</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected($categoriaElegida === (string) $categoria->id)>{{ $categoria->nombre }}{{ $categoria->temporada ? ' · '.$categoria->temporada->nombre : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="clubes" class="block text-sm text-gray-300 font-medium mb-1">Clubes (opcional)</label>
                <select id="clubes" name="clubes[]" multiple size="6" class="{{ $campo }}" aria-describedby="ayuda-clubes">
                    @foreach ($clubes as $club)
                        <option value="{{ $club->id }}" @selected(in_array((string) $club->id, $clubesElegidos, true))>{{ $club->nombre }}</option>
                    @endforeach
                </select>
                <p id="ayuda-clubes" class="mt-1 text-xs text-gray-400">Sin elegir ninguno, el comunicado va a todos los clubes. Con categoría o clubes, solo reciben las cuentas ligadas a un club.</p>
            </div>
        </div>

        <button type="submit" class="px-4 py-2 bg-gray-600 hover:bg-gray-500 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">Revisar destinatarios</button>
    </form>
</div>
@endsection
