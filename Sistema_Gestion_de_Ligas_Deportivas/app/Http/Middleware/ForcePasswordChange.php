<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Si el usuario está autenticado y requiere cambio de contraseña
        if ($user && $user->requires_password_change) {
            // Permitir el paso solo si ya están en las rutas específicas de cambio de contraseña
            if (! $request->routeIs('password.force-change.*')) {
                return redirect()->route('password.force-change.show');
            }
        }

        return $next($request);
    }
}
