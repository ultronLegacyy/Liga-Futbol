<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Http\Responses\LogoutResponse as CustomLogoutResponse;
use App\Models\User;
use App\Services\BruteForceProtectionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Registrar servicios en el contenedor de dependencias.
     */
    public function register(): void
    {
        // Enlace de las respuestas personalizadas de inicio y cierre de sesión
        $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);
        $this->app->singleton(LogoutResponse::class, CustomLogoutResponse::class);
        $this->app->singleton(BruteForceProtectionService::class);
    }

    /**
     * Inicializar servicios de autenticación y reglas de negocio.
     */
    public function boot(): void
    {
        // Asignación de la vista visual del formulario de login
        Fortify::loginView(function () {
            return view('auth.login');
        });

        // Validación personalizada de autenticación con protección contra fuerza bruta (RNF-28)
        Fortify::authenticateUsing(function (Request $request) {
            $bruteForce = app(BruteForceProtectionService::class);
            $campoIdentificador = Fortify::username();
            $credencial = (string) $request->input($campoIdentificador);
            $password = (string) $request->input('password');
            $ip = (string) $request->ip();

            // 1. Verificación previa: ¿la cuenta está en bloqueo activo de 15 minutos?
            if ($bruteForce->isLocked($credencial)) {
                $minutosRestantes = $bruteForce->getLockoutRemainingMinutes($credencial);

                throw ValidationException::withMessages([
                    $campoIdentificador => [
                        "Su cuenta ha sido bloqueada temporalmente por 15 minutos debido a 5 intentos fallidos consecutivos de inicio de sesión. Por favor intente de nuevo en {$minutosRestantes} minuto(s).",
                    ],
                ]);
            }

            // 2. Búsqueda del modelo de usuario por correo electrónico
            $user = User::where($campoIdentificador, $credencial)->first();

            // 3. Comprobación de credenciales
            if ($user && Hash::check($password, $user->password)) {
                // Restricción estricta: solo usuarios con estatus activo pueden ingresar al sistema
                if (isset($user->estatus) && $user->estatus !== 'activo') {
                    throw ValidationException::withMessages([
                        $campoIdentificador => ['Su cuenta no se encuentra activa en el sistema. Contacte a la administracion.'],
                    ]);
                }

                // Éxito: se reinicia el contador de intentos fallidos a cero (RNF-28)
                $bruteForce->clearAttempts($credencial);

                // Registro del momento exacto del inicio de actividad para la auditoría de timeout
                session(['last_activity_time' => now()->timestamp]);

                return $user;
            }

            // 4. Intento fallido: registrar en el servicio de fuerza bruta
            $intentos = $bruteForce->recordFailedAttempt($credencial, $ip, $request);

            if ($intentos >= BruteForceProtectionService::MAX_ATTEMPTS) {
                $minutosRestantes = $bruteForce->getLockoutRemainingMinutes($credencial);

                throw ValidationException::withMessages([
                    $campoIdentificador => [
                        "Su cuenta ha sido bloqueada temporalmente por 15 minutos debido a 5 intentos fallidos consecutivos de inicio de sesión. Por favor intente de nuevo en {$minutosRestantes} minuto(s).",
                    ],
                ]);
            }

            $restantes = BruteForceProtectionService::MAX_ATTEMPTS - $intentos;

            throw ValidationException::withMessages([
                $campoIdentificador => [
                    "Credenciales incorrectas. Le quedan {$restantes} intento(s) antes del bloqueo temporal de la cuenta por 15 minutos.",
                ],
            ]);
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Límite de peticiones de login por dirección IP (rate limiting de red RNF-28)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())->response(function () {
                return response('Demasiadas solicitudes de inicio de sesión desde esta dirección IP. Por favor intente más tarde.', 429);
            });
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
