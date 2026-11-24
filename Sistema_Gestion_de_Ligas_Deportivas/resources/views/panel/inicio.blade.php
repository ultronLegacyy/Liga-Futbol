@extends('layouts.admin')

@section('title', 'Inicio - SGLD')
@section('header', 'Inicio')

@section('content')
    @php($iconos = \App\Support\MenuPanel::ICONOS)

    {{-- Portada del inicio con el estilo del portal: azul eléctrico, letra condensada y estelas --}}
    <section class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-marino-800 via-electrico to-marino-900 p-6 text-white shadow-xl sm:p-10" aria-labelledby="saludo">
        <div class="pointer-events-none absolute -right-16 top-1/3 h-40 w-[70%] -rotate-12 -skew-x-12 opacity-60 [background:repeating-linear-gradient(180deg,transparent_0_10px,rgba(124,198,255,.7)_10px_14px,transparent_14px_24px,rgba(255,255,255,.45)_24px_26px,transparent_26px_36px)] [mask-image:linear-gradient(90deg,transparent,#000_40%,#000_85%,transparent)]" aria-hidden="true"></div>
        <p class="relative mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-[.16em]">
            <span class="h-2 w-2 rounded-full bg-celeste" aria-hidden="true"></span>{{ $rol }}
        </p>
        <h2 id="saludo" class="display relative text-5xl sm:text-6xl">{{ $saludo }},<br><span class="text-celeste">{{ \Illuminate\Support\Str::of($usuario->name)->before(' ') }}</span></h2>
        <p class="relative mt-4 max-w-xl text-base text-white/90">Estas son las áreas de la liga a las que tienes acceso. Elige una para empezar.</p>
    </section>

    @forelse ($areas as $grupo)
        <section class="mb-8" aria-labelledby="area-{{ $loop->index }}">
            <h2 id="area-{{ $loop->index }}" class="display mb-4 text-3xl text-white">
                <span class="border-b-4 border-electrico pb-1">{{ $grupo['area'] }}</span>
            </h2>
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($grupo['opciones'] as $opcion)
                    <li>
                        <a href="{{ route($opcion['ruta']) }}" class="group flex h-full items-start gap-4 rounded-2xl border border-gray-700 bg-gray-800 p-5 transition hover:-translate-y-1 hover:border-electrico hover:shadow-lg hover:shadow-electrico/20">
                            <span class="grid h-12 w-12 flex-none place-items-center rounded-xl bg-electrico/20 text-celeste group-hover:bg-electrico group-hover:text-white">
                                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconos[$opcion['icono']] }}"/></svg>
                            </span>
                            <span>
                                <span class="block font-semibold text-white">{{ $opcion['texto'] }}</span>
                                <span class="mt-1 block text-sm text-gray-300">{{ $opcion['descripcion'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-600 bg-gray-800 p-6" role="status">
            <p class="font-semibold text-white">Tu cuenta todavía no tiene módulos asignados.</p>
            <p class="mt-1 text-sm text-gray-300">Pide a la Secretaría de la liga que revise tu rol. Mientras tanto puedes consultar el <a href="{{ route('portal.programacion') }}" class="text-celeste underline">portal público</a>.</p>
        </div>
    @endforelse
@endsection
