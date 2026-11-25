<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- HU-022 (RNF-30): las pantallas internas tienen datos personales y no se indexan --}}
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#071a4f">
    <title>@yield('title', 'Sistema de Gestión de Ligas Deportivas')</title>
    {{--
        HAB-S7 (S7-T01): Tailwind compilado con Vite (npm run build o npm run dev). Mientras un equipo no compile
        los assets, el panel usa el CDN anterior con la misma paleta, para que nadie se quede sin estilos.
    --}}
    @if (is_file(public_path('hot')) || is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            gray: { 50: '#f3f6fc', 100: '#e6ecf7', 200: '#cdd8ec', 300: '#b3c1dd', 400: '#8b9cc2', 500: '#64779f', 600: '#435683', 700: '#2b3c68', 750: '#21305a', 800: '#18264c', 900: '#0d1736', 950: '#070e24' },
                            green: { 50: '#eef3ff', 100: '#dbe6ff', 200: '#bccfff', 300: '#8fb0ff', 400: '#6b9bff', 500: '#3d74ff', 600: '#1f5eff', 700: '#1849d1', 800: '#143ba6', 900: '#112f7f', 950: '#0b1d4f' },
                            marino: { 950: '#040b22', 900: '#071a4f', 800: '#082a86' },
                            electrico: { DEFAULT: '#1f5eff', claro: '#4d7fff' },
                            celeste: '#7cc6ff',
                            foco: '#ffb020',
                        },
                    },
                },
            };
        </script>
        <style>
            .display { font-family: 'Barlow Condensed', 'Roboto Condensed', 'Arial Narrow', Impact, sans-serif; font-weight: 900; font-style: italic; font-stretch: condensed; text-transform: uppercase; letter-spacing: -0.01em; line-height: 0.92; }
            :focus-visible { outline: 3px solid #ffb020; outline-offset: 2px; }
        </style>
    @endif
</head>
@php
    /** @var \App\Models\User|null $usuarioPanel */
    $usuarioPanel = auth()->user();
    $menuPanel = $usuarioPanel ? \App\Support\MenuPanel::para($usuarioPanel) : [];
    $frecuentes = $usuarioPanel ? \App\Support\MenuPanel::frecuentes($usuarioPanel) : [];
    $iconos = \App\Support\MenuPanel::ICONOS;
    $rolPanel = $usuarioPanel?->getRoleNames()->first() ?? 'Sin Rol';
@endphp
<body class="min-h-screen bg-gray-900 text-gray-100 antialiased md:flex">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-foco focus:px-4 focus:py-3 focus:font-bold focus:text-gray-950">Saltar al contenido</a>

    {{-- Menú lateral por área (S7-T02). En el celular se abre desde el menú inferior --}}
    <aside id="menu-lateral" class="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col overflow-y-auto bg-marino-900 text-white shadow-2xl md:sticky md:top-0 md:flex md:h-screen md:w-64 md:shadow-none" aria-label="Menú del panel">
        <div class="flex items-center gap-3 px-5 py-5">
            <svg viewBox="0 0 40 40" class="h-9 w-9 flex-none" aria-hidden="true"><path d="M4 28 22 6h14L18 28Z" fill="#fff"/><path d="M14 34 24 22h12L26 34Z" fill="#7cc6ff"/></svg>
            <div class="leading-none">
                <span class="display block text-2xl">SGLD</span>
                <span class="mt-1 block text-[11px] font-bold uppercase tracking-[.18em] text-celeste">Liga Fútbol</span>
            </div>
            <button type="button" id="cerrar-menu" class="ml-auto grid h-11 w-11 place-items-center rounded-full hover:bg-white/10 md:hidden" aria-label="Cerrar menú">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <nav class="flex-1 space-y-5 px-3 pb-4">
            <a href="{{ route('panel.inicio') }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold {{ request()->routeIs('panel.inicio') ? 'bg-electrico text-white shadow-lg shadow-electrico/30' : 'text-gray-200 hover:bg-white/10 hover:text-white' }}" @if (request()->routeIs('panel.inicio')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" class="h-5 w-5 flex-none" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconos['inicio'] }}"/></svg>
                Inicio
            </a>

            @foreach ($menuPanel as $grupo)
                <div>
                    <p class="px-3 pb-2 text-[11px] font-bold uppercase tracking-[.16em] text-celeste/90">{{ $grupo['area'] }}</p>
                    <div class="space-y-1">
                        @foreach ($grupo['opciones'] as $opcion)
                            @php($activa = request()->routeIs($opcion['activa']))
                            <a href="{{ route($opcion['ruta']) }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium {{ $activa ? 'bg-electrico text-white shadow-lg shadow-electrico/30' : 'text-gray-200 hover:bg-white/10 hover:text-white' }}" @if ($activa) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" class="h-5 w-5 flex-none" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconos[$opcion['icono']] }}"/></svg>
                                {{ $opcion['texto'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="space-y-1 border-t border-white/10 p-3">
            <a href="{{ route('portal.programacion') }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm text-gray-200 hover:bg-white/10 hover:text-white">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconos['portal'] }}"/></svg>
                Ver el portal público
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-semibold text-red-300 hover:bg-white/10">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconos['salir'] }}"/></svg>
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </aside>
    <div id="fondo-menu" class="fixed inset-0 z-40 hidden bg-gray-950/70 md:hidden"></div>

    <div class="flex min-h-screen min-w-0 flex-1 flex-col pb-20 md:pb-0">
        {{-- Barra superior con el título de la pantalla y el rol --}}
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-gray-700/70 bg-gray-900/90 px-4 backdrop-blur sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <svg viewBox="0 0 40 40" class="h-7 w-7 flex-none md:hidden" aria-hidden="true"><path d="M4 28 22 6h14L18 28Z" fill="#fff"/><path d="M14 34 24 22h12L26 34Z" fill="#7cc6ff"/></svg>
                <h1 class="truncate text-lg font-semibold text-white">@yield('header', 'Panel de Administración')</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden text-sm text-gray-300 sm:inline">{{ $usuarioPanel->name ?? 'Usuario' }}</span>
                <span class="rounded-full border border-celeste/40 bg-electrico/20 px-3 py-1 text-xs font-semibold text-celeste">{{ $rolPanel }}</span>
            </div>
        </header>

        <main id="contenido" tabindex="-1" class="flex-1 p-4 sm:p-6">
            @yield('content')
        </main>
    </div>

    {{-- Menú inferior en el celular: Inicio, las tres opciones principales del rol y el menú completo --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-white/10 bg-marino-900/95 pb-[env(safe-area-inset-bottom)] text-white backdrop-blur md:hidden" aria-label="Menú rápido">
        @php($inicioActivo = request()->routeIs('panel.inicio'))
        <a href="{{ route('panel.inicio') }}" class="flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] font-semibold {{ $inicioActivo ? 'text-celeste' : 'text-gray-200' }}" @if ($inicioActivo) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconos['inicio'] }}"/></svg>
            Inicio
        </a>
        @foreach ($frecuentes as $opcion)
            @php($activa = request()->routeIs($opcion['activa']))
            <a href="{{ route($opcion['ruta']) }}" class="flex min-h-16 flex-col items-center justify-center gap-1 px-1 text-center text-[11px] font-semibold leading-tight {{ $activa ? 'text-celeste' : 'text-gray-200' }}" @if ($activa) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $iconos[$opcion['icono']] }}"/></svg>
                <span class="line-clamp-1">{{ $opcion['texto'] }}</span>
            </a>
        @endforeach
        @for ($i = count($frecuentes); $i < 3; $i++)
            <span aria-hidden="true"></span>
        @endfor
        <button type="button" id="boton-menu" class="flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] font-semibold text-gray-200" aria-controls="menu-lateral" aria-expanded="false">
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="{{ $iconos['menu'] }}"/></svg>
            Menú
        </button>
    </nav>

    <script>
        // Menú completo en el celular: se abre desde el menú inferior y se cierra con el botón, el fondo o Escape
        (function () {
            const boton = document.getElementById('boton-menu');
            const menu = document.getElementById('menu-lateral');
            const fondo = document.getElementById('fondo-menu');
            const cerrar = document.getElementById('cerrar-menu');

            if (!boton || !menu || !fondo) {
                return;
            }

            const alternar = (abrir) => {
                menu.classList.toggle('hidden', !abrir);
                menu.classList.toggle('flex', abrir);
                fondo.classList.toggle('hidden', !abrir);
                boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
                if (abrir) {
                    menu.querySelector('a, button')?.focus();
                }
            };

            boton.addEventListener('click', () => alternar(menu.classList.contains('hidden')));
            fondo.addEventListener('click', () => alternar(false));
            cerrar?.addEventListener('click', () => { alternar(false); boton.focus(); });
            document.addEventListener('keydown', (evento) => {
                if (evento.key === 'Escape' && boton.getAttribute('aria-expanded') === 'true') {
                    alternar(false);
                    boton.focus();
                }
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
