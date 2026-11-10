@extends('layouts.admin')

@section('title', 'Asambleas de delegados')

@section('content')
<main class="mx-auto max-w-6xl p-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-400">HU-053 · RF-14.1</p>
            <h1 class="mt-2 text-3xl font-bold text-white">Asambleas de delegados</h1>
            <p class="mt-2 text-gray-300">Convocatorias y listas de asistencia de las reuniones formales de la Liga.</p>
        </div>
        @can('create', App\Models\Asamblea::class)
            <a href="{{ route('asambleas.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500">Convocar asamblea</a>
        @endcan
    </div>

    @if (session('success'))
        <div role="status" class="mb-6 rounded-lg border border-emerald-700 bg-emerald-950 p-4 text-emerald-200">{{ session('success') }}</div>
    @endif

    @if ($asambleas->isEmpty())
        <div role="status" class="rounded-lg border border-gray-700 bg-gray-800 p-5 text-gray-300">Todavía no hay asambleas convocadas.</div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-700 bg-gray-800">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <caption class="sr-only">Asambleas convocadas</caption>
                <thead class="bg-gray-900/60 text-left text-xs uppercase tracking-wider text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3">Fecha</th>
                        <th scope="col" class="px-4 py-3">Tipo</th>
                        <th scope="col" class="px-4 py-3">Lugar</th>
                        <th scope="col" class="px-4 py-3">Estado</th>
                        <th scope="col" class="px-4 py-3">Asistentes</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @foreach ($asambleas as $asamblea)
                        <tr>
                            <td class="px-4 py-3">{{ $asamblea->fecha->format('d/m/Y') }} @if ($asamblea->hora) · {{ substr($asamblea->hora, 0, 5) }} @endif</td>
                            <td class="px-4 py-3">{{ $asamblea->tipo }}</td>
                            <td class="px-4 py-3">{{ $asamblea->lugar ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $asamblea->estado }}</td>
                            <td class="px-4 py-3">{{ $asamblea->tieneAsistenciaRegistrada() ? $asamblea->asistentes_count : 'Sin registrar' }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('asambleas.show', $asamblea) }}" class="font-semibold text-emerald-400 hover:text-emerald-300">Ver</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $asambleas->links() }}</div>
    @endif
</main>
@endsection
