<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña | Liga Municipal de Fútbol</title>
    {{-- Mismo Tailwind por CDN que el resto del sistema: el proyecto no compila assets con Vite --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center p-6">
    <main class="w-full max-w-md">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 sm:p-8">
            <h1 class="text-xl font-bold text-white mb-2">Recuperar contraseña</h1>
            <p class="text-sm text-gray-400 mb-6">Escribe el correo de tu cuenta y te enviaremos un enlace para definir una contraseña nueva.</p>

            @if (session('status'))
                <div role="status" class="mb-6 px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-300 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" novalidate class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-200 mb-1">Correo electrónico</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" maxlength="255"
                        class="w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400 {{ $errors->has('email') ? 'border-red-500' : 'border-gray-600' }}"
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')
                        <p id="email-error" class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full px-4 py-3 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400 transition">
                    Enviar enlace
                </button>
            </form>

            <p class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-white underline focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">
                    Volver al inicio de sesión
                </a>
            </p>
        </div>
    </main>
</body>
</html>
