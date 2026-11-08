@extends('layouts.admin')

@section('title', 'Registrar cargo - SGLD')
@section('header', 'Cuotas y cargos')

@section('content')
@php
    $campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400';
    $borde = fn (string $nombre) => $errors->has($nombre) ? 'border-red-500' : 'border-gray-600';
    $tipos = App\Http\Controllers\CargoFinancieroController::TIPOS;
@endphp

<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-white mb-1">Registrar cuota o cargo</h2>
    <p class="text-sm text-gray-400 mb-6">El cargo se registra como pendiente; los pagos se capturan aparte.</p>

    @if ($errors->any())
        <x-alert type="error" message="No se registró el cargo. Revisa los campos marcados." />
    @endif

    @if (! $tipo)
        {{-- Paso 1: el tipo y la temporada deciden qué campos se piden (sin JavaScript) --}}
        <form method="GET" action="{{ route('cargos.create') }}" class="space-y-5">
            <x-form.field name="tipo" label="Tipo de cargo" required :error="$errors->first('tipo')">
                <select id="tipo" name="tipo" required class="{{ $campo }} {{ $borde('tipo') }}" @if ($errors->has('tipo')) aria-invalid="true" aria-describedby="tipo-error" @endif>
                    <option value="">Selecciona el tipo de cargo</option>
                    @foreach ($tipos as $valor => $caso)
                        <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $caso['etiqueta'] }}</option>
                    @endforeach
                </select>
            </x-form.field>

            <x-form.field name="temporada_id" label="Temporada" required help="Solo aparecen las temporadas que no están cerradas." :error="$errors->first('temporada_id')">
                <select id="temporada_id" name="temporada_id" required class="{{ $campo }} {{ $borde('temporada_id') }}" aria-describedby="temporada_id-ayuda @if ($errors->has('temporada_id')) temporada_id-error @endif" @if ($errors->has('temporada_id')) aria-invalid="true" @endif>
                    <option value="">Selecciona una temporada</option>
                    @foreach ($temporadas as $opcion)
                        <option value="{{ $opcion->id }}" @selected((string) request('temporada_id') === (string) $opcion->id)>{{ $opcion->nombre }}</option>
                    @endforeach
                </select>
            </x-form.field>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">Continuar</button>
                <a href="{{ route('cargos.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">Cancelar</a>
            </div>
        </form>
    @else
        @php($caso = $tipos[$tipo])

        {{-- Paso 2: solo los campos que necesita este caso --}}
        <p class="mb-5 text-sm text-gray-300">
            <span class="font-semibold text-white">{{ $caso['etiqueta'] }}</span> · Temporada {{ $temporada->nombre }}
            · <a href="{{ route('cargos.create') }}" class="text-blue-400 underline focus:outline-none focus:ring-2 focus:ring-blue-400 rounded">Cambiar</a>
        </p>

        <form method="POST" action="{{ route('cargos.store') }}" novalidate class="space-y-5">
            @csrf
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="temporada_id" value="{{ $temporada->id }}">

            @if ($errors->hasAny(['tipo', 'temporada_id']))
                <p role="alert" class="text-sm text-red-400">{{ $errors->first('tipo') ?: $errors->first('temporada_id') }}</p>
            @endif

            <x-form.field name="club_id" label="Club al que se carga" required :error="$errors->first('club_id')">
                <select id="club_id" name="club_id" required autofocus class="{{ $campo }} {{ $borde('club_id') }}" @if ($errors->has('club_id')) aria-invalid="true" aria-describedby="club_id-error" @endif>
                    <option value="">Selecciona un club</option>
                    @foreach ($clubes as $club)
                        <option value="{{ $club->id }}" @selected((string) old('club_id') === (string) $club->id)>{{ $club->nombre }}</option>
                    @endforeach
                </select>
            </x-form.field>

            @if ($caso['equipo'] !== 'no')
                <x-form.field name="equipo_id" label="Equipo" :required="$caso['equipo'] === 'obligatorio'" help="Equipos de esta temporada; debe ser del club seleccionado." :error="$errors->first('equipo_id')">
                    <select id="equipo_id" name="equipo_id" @if ($caso['equipo'] === 'obligatorio') required @endif class="{{ $campo }} {{ $borde('equipo_id') }}" aria-describedby="equipo_id-ayuda @if ($errors->has('equipo_id')) equipo_id-error @endif" @if ($errors->has('equipo_id')) aria-invalid="true" @endif>
                        <option value="">{{ $caso['equipo'] === 'obligatorio' ? 'Selecciona un equipo' : 'Sin equipo' }}</option>
                        @foreach ($equipos as $equipo)
                            <option value="{{ $equipo->id }}" @selected((string) old('equipo_id') === (string) $equipo->id)>{{ $equipo->nombre }} ({{ $equipo->club?->nombre }})</option>
                        @endforeach
                    </select>
                </x-form.field>
            @endif

            @if ($caso['persona'] !== 'no')
                <x-form.field name="persona_id" label="Jugador" required help="Personas con rol de jugador en esta temporada." :error="$errors->first('persona_id')">
                    <select id="persona_id" name="persona_id" required class="{{ $campo }} {{ $borde('persona_id') }}" aria-describedby="persona_id-ayuda @if ($errors->has('persona_id')) persona_id-error @endif" @if ($errors->has('persona_id')) aria-invalid="true" @endif>
                        <option value="">Selecciona un jugador</option>
                        @foreach ($jugadores as $jugador)
                            <option value="{{ $jugador->id }}" @selected((string) old('persona_id') === (string) $jugador->id)>{{ $jugador->apellidos }}, {{ $jugador->nombre }}</option>
                        @endforeach
                    </select>
                </x-form.field>
            @endif

            <x-form.field name="monto" label="Monto (MXN)" required help="Mayor que cero, con dos decimales como máximo." :error="$errors->first('monto')">
                <input type="number" id="monto" name="monto" required min="0.01" step="0.01" inputmode="decimal" value="{{ old('monto') }}"
                    class="{{ $campo }} sm:max-w-xs {{ $borde('monto') }}" aria-describedby="monto-ayuda @if ($errors->has('monto')) monto-error @endif" @if ($errors->has('monto')) aria-invalid="true" @endif>
            </x-form.field>

            <x-form.field name="vencimiento" label="Fecha de vencimiento" required :error="$errors->first('vencimiento')">
                <input type="date" id="vencimiento" name="vencimiento" required value="{{ old('vencimiento') }}"
                    class="{{ $campo }} sm:max-w-xs {{ $borde('vencimiento') }}" @if ($errors->has('vencimiento')) aria-invalid="true" aria-describedby="vencimiento-error" @endif>
            </x-form.field>

            <p class="text-xs text-gray-400"><span class="text-red-400" aria-hidden="true">*</span> Campo obligatorio.</p>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">Registrar</button>
                <a href="{{ route('cargos.index') }}" class="px-4 py-2 text-sm text-gray-300 rounded-lg border border-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400">Cancelar</a>
            </div>
        </form>
    @endif
</div>
@endsection
