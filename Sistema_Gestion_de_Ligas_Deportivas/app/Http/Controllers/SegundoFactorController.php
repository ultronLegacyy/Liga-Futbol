<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;

// HU-082: activar el segundo factor (TOTP de Fortify), confirmarlo y ver o regenerar los códigos de recuperación
class SegundoFactorController extends Controller
{
    // Fortify ya pone el estado en la sesión al confirmar o regenerar: ahí se ven los códigos nuevos una vez
    private const ESTADOS_CON_CODIGOS = ['two-factor-authentication-confirmed', 'recovery-codes-generated'];

    public function show(Request $request): View
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $confirmada = Date::now()->unix() - (int) $request->session()->get('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);

        // Las rutas de Fortify piden la contraseña por POST; si falta, se confirma primero y se vuelve aquí (no al POST)
        if (! $confirmada) {
            $request->session()->put('url.intended', route('segundo-factor.show'));
        }

        $pendiente = $usuario->two_factor_secret !== null && $usuario->two_factor_confirmed_at === null;
        $mostrarCodigos = $usuario->hasEnabledTwoFactorAuthentication()
            && ($request->routeIs('segundo-factor.codigos') || in_array($request->session()->get('status'), self::ESTADOS_CON_CODIGOS, true));

        return view('seguridad.segundo-factor', [
            'usuario' => $usuario,
            'obligado' => $usuario->requiereSegundoFactor(),
            'activo' => $usuario->hasEnabledTwoFactorAuthentication(),
            'pendiente' => $pendiente,
            'contrasenaConfirmada' => $confirmada,
            'qr' => $pendiente ? $usuario->twoFactorQrCodeSvg() : null,
            'clave' => $pendiente ? decrypt((string) $usuario->two_factor_secret) : null,
            'codigos' => $mostrarCodigos ? $usuario->recoveryCodes() : [],
        ]);
    }
}
