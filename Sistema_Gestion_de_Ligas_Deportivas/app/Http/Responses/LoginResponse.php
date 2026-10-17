<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Crear una respuesta HTTP tras un inicio de sesion exitoso.
     */
    public function toResponse($request): Response
    {
        // Establecimiento de la marca de tiempo para control de inactividad de sesion
        session(['last_activity_time' => now()->timestamp]);

        // Respuesta en formato JSON para clientes API o peticiones asincronas
        if ($request->wantsJson()) {
            return new JsonResponse([
                'two_factor' => false,
                'message' => 'Inicio de sesion exitoso.',
            ], 200);
        }

        // Redireccion al destino previsto o a la ruta principal tras autenticacion
        return redirect()->intended(config('fortify.home', '/'));
    }
}
