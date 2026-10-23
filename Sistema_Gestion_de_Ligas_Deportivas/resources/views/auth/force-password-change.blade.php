<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambio de Contraseña Obligatorio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-900 flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full px-6 py-8 bg-white shadow-md overflow-hidden sm:rounded-lg">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-4">Cambio de Contraseña</h2>
        <p class="text-sm text-gray-600 mb-6 text-center">Por políticas de seguridad (RNF-26), debes cambiar tu contraseña temporal antes de acceder al sistema.</p>

        <form method="POST" action="{{ route('password.force-change.update') }}">
            @csrf
            
            <div class="mb-4">
                <label for="password" class="block font-medium text-sm text-gray-700">Nueva Contraseña</label>
                <input id="password" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" type="password" name="password" required autofocus>
                @error('password')
                    <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div class="mb-6">
                <label for="password_confirmation" class="block font-medium text-sm text-gray-700">Confirmar Contraseña</label>
                <input id="password_confirmation" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" type="password" name="password_confirmation" required>
                @error('password_confirmation')
                    <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Actualizar Contraseña
                </button>
            </div>
        </form>
    </div>
</body>
</html>