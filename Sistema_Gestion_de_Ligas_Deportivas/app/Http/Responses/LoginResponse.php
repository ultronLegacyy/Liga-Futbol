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
        $destino = $this->obtenerRutaPorRol($usuario instanceof User ? $usuario : null);

        // Redireccion al destino previsto o a la ruta calculada por rol tras autenticacion
        return redirect()->intended($destino);
    }

    /**
     * Determina la ruta de aterrizaje inicial tras el inicio de sesion segun el rol del usuario.
     */
    private function obtenerRutaPorRol(?User $user): string
    {
        return $user?->rutaInicio() ?? (string) config('fortify.home', '/');
    }
}
