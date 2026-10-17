<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * Crear una respuesta HTTP tras cerrar la sesion de usuario.
     */
    public function toResponse($request): Response
    {
        // Limpieza exhaustiva de variables de sesion e invalidacion de estado
        if ($request->hasSession()) {
            $request->session()->forget('last_activity_time');
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Respuesta en formato JSON para clientes API
        if ($request->wantsJson()) {
            return new JsonResponse([
                'message' => 'Sesion cerrada exitosamente.',
            ], 200);
        }

        // Redireccion al formulario de login con mensaje de confirmacion
        return redirect()->route('login')->with('status', 'Sesion cerrada exitosamente.');
    }
}
