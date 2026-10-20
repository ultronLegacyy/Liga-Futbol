<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | Liga Municipal de Fútbol</title>

    <style>
        :root {
            --fondo: #101512;
            --tarjeta: #191f1b;
            --verde: #176b45;
            --verde-hover: #238658;
            --verde-claro: #8bd6a9;
            --borde: #303a33;
            --texto: #f3f6f3;
            --texto-secundario: #a4afa7;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--texto);
            background:
                radial-gradient(
                    ellipse at 50% 0%,
                    rgba(35, 134, 88, 0.16),
                    transparent 55%
                ),
                var(--fondo);
        }

        .login-wrapper {
            width: 100%;
            max-width: 410px;
        }

        .brand {
            margin-bottom: 28px;
            text-align: center;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(80, 177, 123, 0.55);
            border-radius: 14px;
            color: var(--verde-claro);
            background: rgba(35, 134, 88, 0.12);
            font-size: 23px;
            font-weight: 700;
        }

        .brand h1 {
            margin: 0 0 8px;
            font-size: 23px;
            font-weight: 650;
            letter-spacing: -0.5px;
        }

        .brand p {
            margin: 0;
            color: var(--texto-secundario);
            font-size: 13px;
            line-height: 1.6;
        }

        .login-card {
            padding: 30px;
            border: 1px solid var(--borde);
            border-radius: 14px;
            background: var(--tarjeta);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.22);
        }

        .section-heading {
            margin-bottom: 25px;
        }

        .section-heading h2 {
            margin: 0 0 7px;
            font-size: 20px;
            font-weight: 600;
        }

        .section-heading p {
            margin: 0;
            color: var(--texto-secundario);
            font-size: 13px;
            line-height: 1.6;
        }

        .alert {
            margin-bottom: 20px;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }

        .alert-status {
            color: #a8edc2;
            border: 1px solid #285c40;
            background: #172d20;
        }

        .alert-danger {
            color: #ffb4b4;
            border: 1px solid #693737;
            background: #301c1c;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #e4eae5;
            font-size: 13px;
            font-weight: 600;
        }

        .input-wrap {
            position: relative;
        }

        .form-control {
            width: 100%;
            min-height: 46px;
            padding: 12px 13px;
            border: 1px solid #3b463e;
            border-radius: 8px;
            outline: none;
            color: var(--texto);
            background: #111713;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control::placeholder {
            color: #77847a;
        }

        .form-control:focus {
            border-color: #47a874;
            box-shadow: 0 0 0 3px rgba(71, 168, 116, 0.13);
        }

        .password-input {
            padding-right: 78px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            padding: 5px 0;
            border: 0;
            color: var(--verde-claro);
            background: transparent;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
        }

        .toggle-password:hover {
            color: #ffffff;
        }

        .form-options {
            display: flex;
            align-items: center;
            margin: 3px 0 23px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: var(--texto-secundario);
            font-size: 13px;
            cursor: pointer;
        }

        .remember input {
            width: 15px;
            height: 15px;
            margin: 0;
            accent-color: var(--verde-hover);
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            min-height: 47px;
            padding: 12px;
            border: 1px solid #278456;
            border-radius: 8px;
            color: #ffffff;
            background: var(--verde);
            font-family: inherit;
            font-size: 14px;
            font-weight: 650;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .btn-submit:hover {
            background: var(--verde-hover);
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        .btn-submit:focus-visible,
        .toggle-password:focus-visible {
            outline: 2px solid var(--verde-claro);
            outline-offset: 3px;
        }

        .footer {
            margin-top: 23px;
            color: #7e8a81;
            text-align: center;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .footer span {
            color: #4eaf79;
        }

        @media (max-width: 480px) {
            body {
                padding: 18px;
            }

            .login-card {
                padding: 24px 20px;
            }

            .brand h1 {
                font-size: 21px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }
    </style>
</head>

<body>
    <main class="login-wrapper">

        <header class="brand">
            <div class="brand-mark" aria-hidden="true">L</div>

            <h1>Liga Municipal de Fútbol</h1>

            <p>Sistema de Gestión de Ligas Deportivas</p>
        </header>

        <section class="login-card">

            <div class="section-heading">
                <h2>Bienvenido</h2>

                <p>
                    Ingresa tus credenciales para acceder al sistema.
                </p>
            </div>

            {{-- Mensajes de estado --}}
            @if (session('status'))
                <div class="alert alert-status" role="status">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Errores de validación o autenticación --}}
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Autenticación real de Laravel Fortify --}}
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label for="email" class="form-label">
                        Correo electrónico
                    </label>

                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-control"
                        placeholder="nombre@correo.com"
                        required
                        autofocus
                        autocomplete="username"
                        inputmode="email"
                        autocapitalize="none"
                        spellcheck="false"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        Contraseña
                    </label>

                    <div class="input-wrap">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            class="form-control password-input"
                            placeholder="Ingresa tu contraseña"
                            required
                            autocomplete="current-password"
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                            aria-controls="password"
                            aria-pressed="false"
                        >
                            Mostrar
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember" for="remember">
                        <input
                            id="remember"
                            type="checkbox"
                            name="remember"
                            value="1"
                        >

                        Recordar mi sesión
                    </label>
                </div>

                <button type="submit" class="btn-submit">
                    Iniciar sesión
                </button>
            </form>

        </section>

        <footer class="footer">
            GESTIÓN DEPORTIVA <span>·</span> ACCESO AUTORIZADO
        </footer>

    </main>

    <script>
        (() => {
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');

            if (!passwordInput || !togglePassword) {
                return;
            }

            togglePassword.addEventListener('click', () => {
                const mostrar = passwordInput.type === 'password';

                passwordInput.type = mostrar ? 'text' : 'password';
                togglePassword.textContent = mostrar ? 'Ocultar' : 'Mostrar';
                togglePassword.setAttribute('aria-pressed', String(mostrar));
            });
        })();
    </script>
</body>
</html>