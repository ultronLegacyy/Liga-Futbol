<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enlace no válido | Liga Municipal de Fútbol</title>
    {{-- Mismo Tailwind por CDN que el resto del sistema: el proyecto no compila assets con Vite --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center p-6">
    <main class="w-full max-w-md">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 sm:p-8">
            <h1 class="text-xl font-bold text-white mb-2">El enlace no es válido o ya venció</h1>
            <p class="text-sm text-gray-400 mb-6">Los enlaces de recuperación solo se pueden usar una vez y vencen después de un tiempo. Solicita uno nuevo para definir tu contraseña.</p>

            <a href="{{ route('password.request') }}"
                class="block w-full text-center px-4 py-3 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                Solicitar un enlace nuevo
            </a>

            <p class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-white underline focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
                    Volver al inicio de sesión
                </a>
            </p>
        </div>
    </main>
</body>
</html>
