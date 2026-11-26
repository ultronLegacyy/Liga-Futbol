<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código del segundo factor | Liga Municipal de Fútbol</title>
    {{-- Mismo Tailwind por CDN que el resto del panel: el proyecto no compila assets con Vite --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center p-6">
    @php($campo = 'w-full rounded-lg bg-gray-900 border text-white p-3 focus:outline-none focus:ring-2 focus:ring-green-400')
    <main class="w-full max-w-md">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 sm:p-8 space-y-6">
            <div>
                <h1 class="text-xl font-bold text-white mb-2">Código del segundo factor</h1>
                <p class="text-sm text-gray-300">Escribe el código de 6 dígitos que muestra tu app de autenticación.</p>
            </div>

            {{-- HU-082: el código de la app (TOTP) --}}
            <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-3" novalidate>
                @csrf
                <label for="code" class="block text-sm font-medium text-gray-200">Código de la app</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                    class="{{ $campo }} text-center tracking-widest {{ $errors->has('code') ? 'border-red-500' : 'border-gray-600' }}"
                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code')
                    <p id="code-error" role="alert" class="text-sm text-red-400">{{ $message }}</p>
                @enderror
                <button type="submit" class="w-full px-4 py-3 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-400">Entrar</button>
            </form>

            {{-- Sin el celular: un código de recuperación, que sirve una sola vez --}}
            <details class="text-sm" @if ($errors->has('recovery_code')) open @endif>
                <summary class="cursor-pointer text-gray-300 hover:text-white">¿No tienes tu celular? Usa un código de recuperación</summary>
                <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-3 mt-3" novalidate>
                    @csrf
                    <label for="recovery_code" class="block text-sm font-medium text-gray-200">Código de recuperación</label>
                    <input id="recovery_code" name="recovery_code" type="text" autocomplete="off" maxlength="40"
                        class="{{ $campo }} font-mono {{ $errors->has('recovery_code') ? 'border-red-500' : 'border-gray-600' }}"
                        @error('recovery_code') aria-invalid="true" aria-describedby="recovery-error" @enderror>
                    @error('recovery_code')
                        <p id="recovery-error" role="alert" class="text-sm text-red-400">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="w-full px-4 py-3 bg-gray-700 text-white text-sm font-semibold rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-green-400">Entrar con el código de recuperación</button>
                    <p class="text-xs text-gray-400">Si perdiste el celular y los códigos, pide al Administrador General que reinicie tu segundo factor.</p>
                </form>
            </details>

            <a href="{{ route('login') }}" class="block text-center text-sm text-gray-400 hover:text-white underline">Volver al inicio de sesión</a>
        </div>
    </main>
</body>
</html>
