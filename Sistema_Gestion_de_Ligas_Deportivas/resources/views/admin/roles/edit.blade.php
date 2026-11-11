@extends('layouts.admin')

@section('title', 'Editar permisos de '.$rol->name.' - SGLD')
@section('header', 'Roles y permisos')

@section('content')
{{-- HU-083 CA1 y CA2: agregar o quitar permisos de módulo y acción, con motivo obligatorio que queda en la bitácora --}}
<div class="bg-gray-800 border border-gray-700 rounded-xl p-4 sm:p-6 max-w-4xl">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-white">Editar permisos de {{ $rol->name }}</h2>
            <p class="text-sm text-gray-400">Marca las acciones que puede hacer el rol. Al guardar, el cambio aplica de inmediato a todas sus cuentas.</p>
        </div>
        <a href="{{ route('admin.roles.show', $rol->id) }}" class="self-start text-gray-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
            Cancelar
        </a>
    </div>

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-300">
            <p class="font-semibold">No se guardaron los permisos:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($protegidos !== [])
        <p class="mb-6 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-4 text-sm text-yellow-200">
            El Administrador General no puede quitarse los permisos de administrar cuentas y permisos (supuesto de PA-18).
        </p>
    @endif

    <form method="POST" action="{{ route('admin.roles.permisos.update', $rol->id) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @php($marcados = old('permisos') !== null ? array_flip((array) old('permisos')) : null)

        @foreach ($modulos as $modulo)
            <fieldset class="border border-gray-700 rounded-lg p-4">
                <legend class="px-2 text-sm font-semibold text-white">{{ $modulo['nombre'] }}</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach ($modulo['acciones'] as $accion)
                        @php($marcado = $marcados !== null ? isset($marcados[$accion['permiso']]) : $accion['permitida'])
                        <label class="flex items-center gap-2 text-sm text-gray-200">
                            <input type="checkbox" name="permisos[]" value="{{ $accion['permiso'] }}" @checked($marcado)
                                class="rounded border-gray-600 bg-gray-900 text-green-500 focus:ring-2 focus:ring-green-400">
                            {{ $accion['nombre'] }}
                            @if (in_array($modulo['clave'], $protegidos, true))
                                <span class="text-xs text-yellow-300">(protegido)</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div>
            <label for="motivo" class="block mb-1 text-sm font-medium text-gray-200">Motivo del cambio</label>
            <textarea id="motivo" name="motivo" rows="3" required minlength="3" maxlength="1000"
                class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400">{{ old('motivo') }}</textarea>
            <p class="mt-1 text-xs text-gray-400">Obligatorio. Queda en la bitácora con tu usuario, la fecha y hora, la IP y los permisos de antes y después.</p>
        </div>

        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
            Guardar permisos
        </button>
    </form>
</div>
@endsection
