<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Acceso Denegado - Sistema de Gestion de Ligas Deportivas</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .error-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 520px;
            padding: 40px;
            text-align: center;
            box-sizing: border-box;
            border-top: 6px solid #dc2626;
        }
        .error-code {
            font-size: 64px;
            font-weight: 800;
            color: #dc2626;
            margin: 0 0 10px 0;
            line-height: 1;
        }
        .error-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 16px 0;
        }
        .error-message {
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
            margin: 0 0 24px 0;
        }
        .info-box {
            background-color: #f1f5f9;
            border-left: 4px solid #64748b;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 13px;
            color: #334155;
            text-align: left;
            margin-bottom: 28px;
        }
        .actions-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: background-color 0.15s ease-in-out;
        }
        .btn-primary {
            background-color: #1e3a8a;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #1e40af;
        }
        .btn-secondary {
            background-color: #e2e8f0;
            color: #334155;
        }
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">403</div>
        <h1 class="error-title">Acceso Restringido</h1>
        <p class="error-message">
            {{ (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : ($mensaje ?? 'No cuenta con los permisos necesarios o el rol autorizado para acceder a este modulo del sistema.') }}
        </p>

        <div class="info-box">
            <strong>Control de Seguridad RBAC:</strong> Esta accion requiere privilegios administrativos especificos concedidos por la Liga Municipal de Futbol Amateur de Morelia, A.C. Si considera que esto es un error, contacte al Administrador General.
        </div>

        <div class="actions-group">
            <a href="javascript:history.back()" class="btn btn-secondary">Regresar</a>
            <a href="{{ url('/') }}" class="btn btn-primary">Ir al Inicio</a>
        </div>
    </div>
</body>
</html>
