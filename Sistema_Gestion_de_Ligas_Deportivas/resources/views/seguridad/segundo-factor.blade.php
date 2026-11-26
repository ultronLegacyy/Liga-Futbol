<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segundo factor de autenticación | Liga Municipal de Fútbol</title>
    {{-- Mismo Tailwind por CDN que el resto del panel: el proyecto no compila assets con Vite --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center p-6">
    @php
        $boton = 'w-full px-4 py-3 text-sm font-semibold rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition';
        $errorCodigo = $errors->confirmTwoFactorAuthentication->first('code');
    @endphp
    <main class="w-full max-w-md">
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 sm:p-8 space-y-6">
            <div>
                <h1 class="text-xl font-bold text-white mb-2">Segundo factor de autenticación</h1>
                @if ($obligado && ! $activo)
                    <p class="text-sm text-gray-300">Tu cuenta tiene privilegios: para usar el sistema debes activar el segundo factor. Necesitas una app de autenticación en tu celular, por ejemplo Proton Authenticator, Google Authenticator o 2FAS.</p>
                @elseif ($activo)
                    <p class="text-sm text-gray-300">El segundo factor está activo. Al iniciar sesión, después de tu contraseña, se te pedirá el código de tu app de autenticación.</p>
                @else
                    <p class="text-sm text-gray-300">Puedes proteger tu cuenta con un código de tu app de autenticación además de tu contraseña.</p>
                @endif
            </div>

            @if (session('status') === 'two-factor-authentication-confirmed')
                <div role="status" class="px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-300 text-sm">Listo: el segundo factor quedó activado.</div>
            @elseif (session('status') === 'recovery-codes-generated')
                <div role="status" class="px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-300 text-sm">Se generaron códigos de recuperación nuevos; los anteriores ya no sirven.</div>
            @elseif (session('status') === 'two-factor-authentication-disabled')
                <div role="status" class="px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-300 text-sm">El segundo factor quedó desactivado.</div>
            @endif

            @if (! $contrasenaConfirmada && ! ($activo && $codigos !== []))
                {{-- Fortify pide la contraseña antes de activar, regenerar o desactivar --}}
                <div class="space-y-3">
                    <p class="text-sm text-gray-300">Por seguridad, primero confirma tu contraseña.</p>
                    <a href="{{ route('password.confirm') }}" class="block text-center {{ $boton }} bg-green-600 text-white hover:bg-green-700">Confirmar contraseña</a>
                </div>
            @elseif (! $activo && ! $pendiente)
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <button type="submit" class="{{ $boton }} bg-green-600 text-white hover:bg-green-700">Activar el segundo factor</button>
                </form>
            @elseif ($pendiente)
                <section aria-labelledby="titulo-qr" class="space-y-4">
                    <h2 id="titulo-qr" class="font-semibold text-white">1. Escanea el código con tu app</h2>
                    <div class="bg-white p-3 rounded-lg w-fit mx-auto" role="img" aria-label="Código QR para tu app de autenticación">{!! $qr !!}</div>
                    <p class="text-sm text-gray-300">¿No puedes escanearlo? Escribe esta clave en la app:</p>
                    <p class="font-mono text-sm text-white bg-gray-900 border border-gray-600 rounded-lg p-3 break-all select-all">{{ $clave }}</p>

                    <h2 class="font-semibold text-white">2. Escribe el código de 6 dígitos que muestra la app</h2>
                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3" novalidate>
                        @csrf
                        <label for="code" class="sr-only">Código de la app</label>
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
                            class="w-full rounded-lg bg-gray-900 border text-white p-3 text-center tracking-widest focus:outline-none focus:ring-2 focus:ring-green-400 {{ $errorCodigo ? 'border-red-500' : 'border-gray-600' }}"
                            @if ($errorCodigo) aria-invalid="true" aria-describedby="code-error" @endif>
                        @if ($errorCodigo)
                            <p id="code-error" class="text-sm text-red-400">{{ $errorCodigo }}</p>
                        @endif
                        <button type="submit" class="{{ $boton }} bg-green-600 text-white hover:bg-green-700">Confirmar y activar</button>
                    </form>
                </section>
            @endif

            @if ($codigos !== [])
                <section aria-labelledby="titulo-codigos" class="space-y-3">
                    <h2 id="titulo-codigos" class="font-semibold text-white">Códigos de recuperación</h2>
                    <p class="text-sm text-gray-300">Guárdalos en un lugar seguro. Si pierdes tu celular, cada código te deja entrar <strong>una sola vez</strong>.</p>
                    <ul class="grid grid-cols-2 gap-2 font-mono text-sm text-white bg-gray-900 border border-gray-600 rounded-lg p-3">
                        @foreach ($codigos as $codigo)
                            <li>{{ $codigo }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($activo && $contrasenaConfirmada)
                <div class="space-y-3">
                    @if ($codigos === [])
                        <a href="{{ route('segundo-factor.codigos') }}" class="block text-center {{ $boton }} bg-gray-700 text-white hover:bg-gray-600">Ver códigos de recuperación</a>
                    @endif
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <button type="submit" class="{{ $boton }} bg-gray-700 text-white hover:bg-gray-600">Generar códigos nuevos</button>
                    </form>
                    {{-- Las cuentas obligadas no lo pueden quitar (decisión 2 de Daniel) --}}
                    @unless ($obligado)
                        <form method="POST" action="{{ route('two-factor.disable') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="{{ $boton }} bg-red-700 text-white hover:bg-red-800">Desactivar el segundo factor</button>
                        </form>
                    @endunless
                </div>
            @elseif ($activo)
                <a href="{{ route('segundo-factor.codigos') }}" class="block text-center {{ $boton }} bg-gray-700 text-white hover:bg-gray-600">Ver o generar códigos de recuperación</a>
            @endif

            <div class="flex flex-col items-center gap-3 text-sm">
                @if (! $obligado || $activo)
                    <a href="{{ $usuario->rutaInicio() }}" class="text-gray-300 hover:text-white underline">Volver al sistema</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-400 hover:text-white underline focus:outline-none focus:ring-2 focus:ring-gray-400 rounded">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
