<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistema de Gestión de Ligas Deportivas')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">

    <!-- Barra Lateral (Sidebar): en el celular se abre con el botón «Menú» para no angostar el contenido -->
    <aside id="menu-lateral" class="hidden md:flex w-64 bg-gray-800 border-r border-gray-700 flex-col min-h-screen fixed inset-y-0 left-0 z-40 overflow-y-auto md:static">
        <div class="p-5 border-b border-gray-700 flex items-center gap-3">
            <div class="w-8 h-8 bg-green-500 rounded flex items-center justify-center font-bold text-black">
                SGLD
            </div>
            <span class="font-bold text-lg tracking-wide text-white">Liga Fútbol</span>
        </div>

        <nav class="flex-1 p-4 space-y-1">
            @role('Administrador General')
                <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Dashboard
                </a>
                <a href="{{ route('admin.gobernanza') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('admin.gobernanza') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Gobernanza
                </a>
                <a href="{{ route('admin.usuarios.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('admin.usuarios.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Usuarios
                </a>
                <a href="{{ route('admin.roles.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('admin.roles.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Roles y permisos
                </a>
            @endrole

            @role('Árbitro en Campo')
                <a href="{{ route('cedulas.mis-partidos') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('cedulas.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Mis partidos
                </a>
            @endrole
            @can('resultados.validar')
                <a href="{{ route('resultados.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('resultados.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Resultados por validar
                </a>
            @endcan
            @can('viewAny', App\Models\Jugador::class)
                <a href="{{ route('jugadores.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('jugadores.index') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Jugadores
                </a>
            @endcan
            @can('jugadores.crear')
                <a href="{{ route('jugadores.create') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('jugadores.create') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Registrar jugadores
                </a>
            @endcan
            @can('link', App\Models\Jugador::class)
                <a href="{{ route('jugadores.vincular.form') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('jugadores.vincular*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Vincular mi cuenta
                </a>
            @endcan
            @can('viewAny', App\Models\Temporada::class)
                <a href="{{ route('temporadas.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('temporadas.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Temporadas
                </a>
            @endcan
            @can('viewAny', App\Models\Cancha::class)
                <a href="{{ route('canchas.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('canchas.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Canchas
                </a>
            @endcan
            @can('viewAny', App\Models\Persona::class)
                <a href="{{ route('personas.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('personas.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Personas
                </a>
            @endcan
            @can('viewAnyHistorial', App\Models\Equipo::class)
                <a href="{{ route('equipos.historial.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('equipos.historial.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Historial de equipos
                </a>
            @endcan
            @can('viewAny', App\Models\Arbitro::class)
                <a href="{{ route('arbitros.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('arbitros.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Árbitros
                </a>
            @endcan
            @can('viewAny', App\Models\CargoFinanciero::class)
                <a href="{{ route('cargos.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('cargos.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Cuotas y cargos
                </a>
                <a href="{{ route('adeudos.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('adeudos.*') ? 'bg-green-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Adeudos por club
                </a>
            @endcan
        </nav>

        <div class="p-4 border-t border-gray-700">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-gray-700 rounded-lg">
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </aside>

    <!-- Contenido Principal -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Barra Superior (Topbar) -->
        <header class="bg-gray-800 border-b border-gray-700 h-16 flex items-center justify-between gap-3 px-4 sm:px-6">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" id="boton-menu" class="md:hidden px-3 py-2 text-sm text-gray-200 border border-gray-600 rounded-lg" aria-controls="menu-lateral" aria-expanded="false">
                    Menú
                </button>
                <h1 class="text-lg font-semibold text-white truncate">@yield('header', 'Panel de Administración')</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden sm:inline text-sm text-gray-300">{{ auth()->user()->name ?? 'Usuario' }}</span>
                <span class="px-2 py-1 text-xs bg-green-500/20 text-green-400 border border-green-500/30 rounded">
                    {{ auth()->user()?->getRoleNames()->first() ?? 'Sin Rol' }}
                </span>
            </div>
        </header>

        <!-- Área de Contenido -->
        <main class="flex-1 p-4 sm:p-6">
            @yield('content')
        </main>
    </div>

    <script>
        // Menú lateral en el celular: se abre y se cierra con el botón, y con Escape
        (function () {
            const boton = document.getElementById('boton-menu');
            const menu = document.getElementById('menu-lateral');

            if (!boton || !menu) {
                return;
            }

            const alternar = (abrir) => {
                menu.classList.toggle('hidden', !abrir);
                menu.classList.toggle('flex', abrir);
                boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
            };

            boton.addEventListener('click', () => alternar(menu.classList.contains('hidden')));
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