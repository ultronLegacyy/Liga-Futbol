<?php

namespace App\Http\Responses;

use App\Models\User;
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

        $usuario = auth()->user();
        $destino = $usuario instanceof User ? $usuario->rutaInicio() : (string) config('fortify.home', '/admin/dashboard');

        // HU-071, HU-072, HU-073 (Criterio 1): Si la URL prevista requiere rol de Administrador General
        // y el usuario autenticado no posee dicho rol, se limpia el destino previsto para evitar error 403.
        $urlPrevia = session()->get('url.intended');
        if ($urlPrevia && str_contains($urlPrevia, '/admin') && ! ($usuario instanceof User && $usuario->hasRole('Administrador General'))) {
            session()->forget('url.intended');

            return redirect($destino);
        }

        // Redireccion al destino previsto o a la ruta calculada por rol tras autenticacion
        return redirect()->intended($destino);
    }
}
