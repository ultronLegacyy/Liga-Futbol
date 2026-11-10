<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sistema de Gestión de Ligas Deportivas')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex">

    <!-- Barra Lateral (Sidebar) -->
    <aside class="w-64 bg-gray-800 border-r border-gray-700 flex flex-col min-h-screen">
        <div class="p-5 border-b border-gray-700 flex items-center gap-3">
            <div class="w-8 h-8 bg-green-500 rounded flex items-center justify-center font-bold text-black">
                SGLD
            </div>
            <span class="font-bold text-lg tracking-wide text-white">Liga Fútbol</span>
        </div>

        <nav class="flex-1 p-4 space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg bg-green-600 text-white">
                Dashboard
            </a>
            <a href="{{ route('admin.gobernanza') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                Gobernanza
            </a>
            <a href="{{ route('admin.usuarios.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                Usuarios
            </a>
            @can('viewAny', App\Models\Jugador::class)
                <a href="{{ route('jugadores.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Jugadores
                </a>
            @endcan
            @can('jugadores.crear')
                <a href="{{ route('jugadores.create') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Registrar jugadores
                </a>
            @endcan
            @can('link', App\Models\Jugador::class)
                <a href="{{ route('jugadores.vincular.form') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Vincular mi cuenta
                </a>
            @endcan
            @can('viewAny', App\Models\Temporada::class)
                <a href="{{ route('temporadas.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Temporadas
                </a>
            @endcan
            @can('viewAny', App\Models\Cancha::class)
                <a href="{{ route('canchas.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Canchas
                </a>
            @endcan
            @can('viewAny', App\Models\Persona::class)
                <a href="{{ route('personas.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Personas
                </a>
            @endcan
            @can('viewAnyHistorial', App\Models\Equipo::class)
                <a href="{{ route('equipos.historial.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Historial de equipos
                </a>
            @endcan
            @can('viewAny', App\Models\Arbitro::class)
                <a href="{{ route('arbitros.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Árbitros
                </a>
            @endcan
            @can('viewAny', App\Models\CargoFinanciero::class)
                <a href="{{ route('cargos.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
                    Cuotas y cargos
                </a>
                <a href="{{ route('adeudos.index') }}" class="flex items-center px-4 py-2.5 text-sm font-medium text-gray-300 rounded-lg hover:bg-gray-700 hover:text-white">
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
    <div class="flex-1 flex flex-col">
        <!-- Barra Superior (Topbar) -->
        <header class="bg-gray-800 border-b border-gray-700 h-16 flex items-center justify-between px-6">
            <h1 class="text-lg font-semibold text-white">@yield('header', 'Panel de Administración')</h1>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-300">{{ auth()->user()->name ?? 'Usuario' }}</span>
                <span class="px-2 py-1 text-xs bg-green-500/20 text-green-400 border border-green-500/30 rounded">
                    {{ auth()->user()->getRoleNames()->first() ?? 'Sin Rol' }}
                </span>
            </div>
        </header>

        <!-- Área de Contenido -->
        <main class="flex-1 p-6">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>