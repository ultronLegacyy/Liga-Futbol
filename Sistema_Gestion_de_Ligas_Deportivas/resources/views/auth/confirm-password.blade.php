<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar contraseña | Liga Municipal de Fútbol</title>
    {{-- Mismo Tailwind por CDN que el resto del panel: el proyecto no compila assets con Vite --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center p-6">
    <main class="w-full max-w-md">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 sm:p-8 space-y-6">
            <div>
                <h1 class="text-xl font-bold text-white mb-2">Confirma tu contraseña</h1>
                <p class="text-sm text-gray-300">Por seguridad, escribe tu contraseña antes de continuar.</p>
            </div>

            <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-3" novalidate>
                @csrf
                <label for="password" class="block text-sm font-medium text-gray-200">Contraseña</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="current-password" maxlength="64"
                    class="w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400 {{ $errors->has('password') ? 'border-red-500' : 'border-gray-600' }}"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <p id="password-error" role="alert" class="text-sm text-red-400">{{ $message }}</p>
                @enderror
                <button type="submit" class="w-full px-4 py-3 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400">Confirmar</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button type="submit" class="text-sm text-gray-400 hover:text-white underline focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">Cerrar sesión</button>
            </form>
        </div>
    </main>
</body>
</html>
