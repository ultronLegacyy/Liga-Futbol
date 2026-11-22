@extends('layouts.admin')

@section('title', 'Asistencia a la asamblea')

@section('content')
<main class="mx-auto max-w-6xl p-6">
    <div class="mb-6">
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">HU-053 · Asistencia</p>
        <h1 class="mt-2 text-3xl font-bold text-white">Asistencia a la asamblea del {{ $asamblea->fecha->format('d/m/Y') }}</h1>
        <p class="mt-2 text-gray-300">Marca los clubes que asistieron y quién los representó. La lista se guarda junto con la convocatoria.</p>
    </div>

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-lg border border-red-700 bg-red-950/40 p-4 text-sm text-red-200">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($clubes->isEmpty())
        <div role="status" class="rounded-lg border border-amber-700 bg-amber-950 p-5 text-amber-100">No hay clubes activos para convocar.</div>
    @else
        <form method="POST" action="{{ route('asambleas.asistencia.update', $asamblea) }}" class="rounded-xl border border-gray-700 bg-gray-800 p-6">
            @csrf
            @method('PUT')
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700 text-sm">
                    <caption class="sr-only">Lista de asistencia por club</caption>
                    <thead class="text-left text-xs uppercase tracking-wider text-gray-400">
                        <tr>
                            <th scope="col" class="py-2 pr-4">Club</th>
                            <th scope="col" class="py-2 pr-4">Asistió</th>
                            <th scope="col" class="py-2 pr-4">Delegado con cuenta</th>
                            <th scope="col" class="py-2 pr-4">Nombre de quien asistió</th>
                            <th scope="col" class="py-2">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-gray-200">
                        @foreach ($clubes as $club)
                            @php($registro = $asistencias->get($club->id))
                            <tr>
                                <th scope="row" class="py-3 pr-4 text-left font-medium text-white">{{ $club->nombre }}</th>
                                <td class="py-3 pr-4">
                                    <input type="hidden" name="asistencias[{{ $club->id }}][asistio]" value="0">
                                    <input id="asistio-{{ $club->id }}" type="checkbox" name="asistencias[{{ $club->id }}][asistio]" value="1" @checked(old("asistencias.{$club->id}.asistio", $registro?->asistio)) class="h-4 w-4 rounded border-gray-500 bg-gray-900 text-emerald-600" aria-label="Asistió {{ $club->nombre }}">
                                </td>
                                <td class="py-3 pr-4">
                                    <select name="asistencias[{{ $club->id }}][delegado_usuario_id]" aria-label="Delegado de {{ $club->nombre }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-2 py-1.5 text-white">
                                        <option value="">Ninguno</option>
                                        @foreach ($delegados->get($club->id, collect()) as $delegado)
                                            <option value="{{ $delegado->id }}" @selected((int) old("asistencias.{$club->id}.delegado_usuario_id", $registro?->delegado_usuario_id) === $delegado->id)>{{ $delegado->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3 pr-4">
                                    <input name="asistencias[{{ $club->id }}][nombre_asistente]" maxlength="200" value="{{ old("asistencias.{$club->id}.nombre_asistente", $registro?->nombre_asistente) }}" aria-label="Nombre de quien asistió por {{ $club->nombre }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-2 py-1.5 text-white">
                                </td>
                                <td class="py-3">
                                    <input name="asistencias[{{ $club->id }}][observaciones]" maxlength="255" value="{{ old("asistencias.{$club->id}.observaciones", $registro?->observaciones) }}" aria-label="Observaciones de {{ $club->nombre }}" class="w-full rounded-lg border border-gray-600 bg-gray-900 px-2 py-1.5 text-white">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-5 flex justify-end gap-3 border-t border-gray-700 pt-5">
                <a href="{{ route('asambleas.show', $asamblea) }}" class="rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 hover:bg-gray-700">Cancelar</a>
                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-500">Guardar asistencia</button>
            </div>
        </form>
    @endif
</main>
@endsection
