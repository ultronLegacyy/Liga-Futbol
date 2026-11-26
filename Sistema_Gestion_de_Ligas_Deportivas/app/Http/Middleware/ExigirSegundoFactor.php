<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HU-082 (criterios 1 y 2): una cuenta con privilegios (decisión 2 de Daniel) sin segundo factor confirmado no opera:
 * cualquier ruta, por GET o POST, la lleva a activarlo. Solo deja pasar lo necesario para activarlo y cerrar sesión.
 */
class ExigirSegundoFactor
{
    private const RUTAS_PERMITIDAS = [
        'segundo-factor.show',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'password.force-change.*',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Una cuenta obligada no puede quitarse el segundo factor, ni con una petición directa
        if ($usuario instanceof User && $request->routeIs('two-factor.disable') && $usuario->requiereSegundoFactor()) {
            abort(403, 'Tu cuenta tiene privilegios: el segundo factor es obligatorio y no se puede desactivar.');
        }

        // La contraseña temporal va primero (ForcePasswordChange); después, el segundo factor
        if ($usuario instanceof User && ! $usuario->requires_password_change && $usuario->debeActivarSegundoFactor()
            && ! $request->routeIs(...self::RUTAS_PERMITIDAS)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Debes activar el segundo factor de autenticación antes de continuar.'], 403)
                : redirect()->route('segundo-factor.show');
        }

        return $next($request);
    }
}
