<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionTimeout
{
    // Lista de roles con jerarquia administrativa sujetos a expiracion corta de 30 minutos
    protected array $adminRoles = [
        'Administrador General',
        'Presidente',
        'Secretario',
        'Tesorero',
        'Comision de la Rama Infantil',
        'Tribunal de Penas',
    ];

    // Tiempo limite de inactividad para administradores en segundos equivalente a 30 minutos
    protected int $adminTimeoutSeconds = 1800;

    // Tiempo limite de inactividad para usuarios operativos en segundos equivalente a 8 horas
    protected int $regularTimeoutSeconds = 28800;

    /**
     * Interceptar la solicitud y validar el tiempo de inactividad segun el rol del usuario.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplica la comprobacion si el usuario tiene una sesion activa
        if (Auth::check()) {
            $user = Auth::user();
            $tiempoActual = now()->timestamp;
            $ultimaActividad = session('last_activity_time');

            // Calculo del umbral maximo de inactividad permitido segun el rol asignado
            $limiteInactividad = $this->obtenerLimiteInactividad($user);

            if ($ultimaActividad !== null) {
                $segundosInactivos = $tiempoActual - (int) $ultimaActividad;

                // Verificacion si el tiempo transcurrido supera el umbral maximo de inactividad
                if ($segundosInactivos > $limiteInactividad) {
                    Auth::logout();

                    $request->session()->forget('last_activity_time');
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $mensajeExpiracion = 'Su sesion ha expirado por inactividad por motivos de seguridad.';

                    // Respuesta diferenciada para solicitudes JSON o navegacion regular
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => $mensajeExpiracion,
                            'session_expired' => true,
                        ], 401);
                    }

                    return redirect()->route('login')->with('status', $mensajeExpiracion);
                }
            }

            // Actualizacion de la marca de tiempo de actividad en cada peticion activa
            session(['last_activity_time' => $tiempoActual]);
        }

        return $next($request);
    }

    /**
     * Obtener el limite de inactividad en segundos evaluando los roles del usuario.
     */
    public function obtenerLimiteInactividad($user): int
    {
        // Se verifica si el usuario posee roles administrativos utilizando el trait HasRoles
        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($this->adminRoles)) {
            return (int) config('session.admin_timeout', $this->adminTimeoutSeconds);
        }

        return (int) config('session.regular_timeout', $this->regularTimeoutSeconds);
    }
}
