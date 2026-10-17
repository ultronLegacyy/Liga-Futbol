<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesion - Sistema de Gestion de Ligas Deportivas</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 100%;
            max-width: 420px;
            padding: 32px;
            box-sizing: border-box;
        }
        .login-title {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            text-align: center;
        }
        .login-subtitle {
            margin-top: 0;
            margin-bottom: 24px;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.4;
        }
        .alert-status {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
            transition: border-color 0.15s ease-in-out;
        }
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-check-input {
            margin-right: 8px;
        }
        .form-check-label {
            font-size: 14px;
            color: #4b5563;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #1d4ed8;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        .btn-submit:hover {
            background-color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1 class="login-title">Liga Municipal de Futbol</h1>
        <p class="login-subtitle">Sistema de Gestion de Ligas Deportivas</p>

        {{-- Notificaciones de sesion o estado del sistema --}}
        @if (session('status'))
            <div class="alert alert-status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Errores de validacion o credenciales incorrectas --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- Formulario de autenticacion procesado mediante Fortify --}}
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Correo Electronico</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contrasena</label>
                <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
            </div>

            <div class="form-check">
                <input id="remember" type="checkbox" name="remember" class="form-check-input">
                <label for="remember" class="form-check-label">Recordar mi sesion</label>
            </div>

            <button type="submit" class="btn-submit">
                Iniciar Sesion
            </button>
        </form>
    </div>
</body>
</html>
