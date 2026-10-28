{{-- Estilos en línea y tablas: los clientes de correo no cargan CSS externo --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asunto }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: Arial, Helvetica, sans-serif; color: #1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 600px; background-color: #ffffff; border-radius: 8px;">
                    <tr>
                        <td style="background-color: #1f2937; padding: 20px 24px; border-radius: 8px 8px 0 0;">
                            <span style="display: inline-block; background-color: #22c55e; color: #000000; font-weight: bold; padding: 4px 8px; border-radius: 4px;">SGLD</span>
                            <span style="color: #ffffff; font-size: 18px; font-weight: bold; padding-left: 8px;">Liga Fútbol</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px;">
                            <h1 style="margin: 0 0 16px; font-size: 20px; color: #111827;">{{ $asunto }}</h1>
                            <p style="margin: 0; font-size: 15px; line-height: 1.6;">{!! nl2br(e($mensaje)) !!}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 24px; background-color: #f9fafb; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px;">
                            Correo automático del Sistema de Gestión de Ligas Deportivas.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
