<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambio de contraseña obligatorio | Liga Municipal de Fútbol</title>
    {{-- Mismo Tailwind por CDN que el resto del panel: el proyecto no compila assets con Vite --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center p-6">
    <main class="w-full max-w-md">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 sm:p-8">
            <h1 class="text-xl font-bold text-white mb-2">Cambia tu contraseña</h1>
            <p class="text-sm text-gray-400 mb-6">Por seguridad (RNF-26), define una contraseña nueva antes de usar el sistema. La temporal deja de servir.</p>

            @if ($errors->any())
                <div role="alert" class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                    No se guardó la contraseña. Revisa los campos marcados.
                </div>
            @endif

            <form method="POST" action="{{ route('password.force-change.update') }}" novalidate class="space-y-5">
                @csrf

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-200 mb-1">Nueva contraseña</label>
                    <input id="password" type="password" name="password" required autofocus autocomplete="new-password" maxlength="64"
                        class="w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400 {{ $errors->has('password') ? 'border-red-500' : 'border-gray-600' }}"
                        aria-describedby="password-ayuda @error('password') password-error @enderror"
                        @error('password') aria-invalid="true" @enderror>
                    <p id="password-ayuda" class="mt-1 text-xs text-gray-400">Entre 15 y 64 caracteres (desde 8 si tienes activo el doble factor).</p>
                    @error('password')
                        <p id="password-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-200 mb-1">Confirmar contraseña</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" maxlength="64"
                        class="w-full rounded-lg bg-gray-900 border border-gray-600 text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400">
                </div>

                <button type="submit" class="w-full px-4 py-3 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                    Guardar contraseña
                </button>
            </form>

            {{-- Salir sin cambiarla: la próxima vez se le vuelve a pedir --}}
            <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
                @csrf
                <button type="submit" class="text-sm text-gray-400 hover:text-white underline focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </main>
</body>
</html>
