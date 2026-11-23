@extends('layouts.admin')

@section('title', 'Registro de correos - SGLD')
@section('header', 'Registro de correos')

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-white">Registro de correos</h2>
        <p class="text-sm text-gray-400">Cada correo que manda el sistema, con su fecha, sus destinatarios y si se envió (HU-067).</p>
    </div>

    <form method="GET" action="{{ route('correos.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label for="estado" class="block text-xs text-gray-300 font-medium mb-1">Estado</label>
            <select name="estado" id="estado" class="rounded-lg bg-gray-900 border border-gray-600 text-white text-sm p-2 focus:ring-2 focus:ring-green-400 focus:outline-none">
                <option value="">Todos</option>
                @foreach (\App\Models\RegistroCorreo::ESTADOS as $opcion)
                    <option value="{{ $opcion }}" @selected($estado === $opcion)>{{ ucfirst($opcion) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">Filtrar</button>
    </form>

    <div class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden">
        @if ($correos->isEmpty())
            <p class="p-6 text-sm text-gray-400">No hay correos registrados{{ $estado !== '' ? ' con ese estado' : '' }}.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-200">
                    <thead class="border-b border-gray-700 bg-gray-900/60 text-xs uppercase tracking-wider text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Fecha</th>
                            <th scope="col" class="px-4 py-3">Tipo</th>
                            <th scope="col" class="px-4 py-3">Asunto</th>
                            <th scope="col" class="px-4 py-3">Destinatarios</th>
                            <th scope="col" class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach ($correos as $correo)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ ($correo->enviado_en ?? $correo->created_at)?->timezone('America/Mexico_City')->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $correo->nombreTipo() }}</td>
                                <td class="px-4 py-3">{{ $correo->asunto }}</td>
                                <td class="px-4 py-3 break-all">{{ implode(', ', $correo->destinatarios) }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :variant="match ($correo->estado) { 'enviado' => 'success', 'fallido' => 'danger', default => 'warning' }">{{ $correo->estado }}</x-badge>
                                    @if ($correo->error)
                                        <span class="block text-xs text-gray-400 mt-1">{{ $correo->error }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $correos->links() }}</div>
        @endif
    </div>
</div>
@endsection
