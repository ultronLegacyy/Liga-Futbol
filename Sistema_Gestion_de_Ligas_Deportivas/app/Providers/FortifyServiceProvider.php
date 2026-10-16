<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Http\Responses\LogoutResponse as CustomLogoutResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        // Enlace de las respuestas personalizadas de inicio y cierre de sesion
        $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);
        $this->app->singleton(LogoutResponse::class, CustomLogoutResponse::class);
    }

    /**
     * Inicializar servicios de autenticacion y reglas de negocio.
     */
    public function boot(): void
    {
        // Asignacion de la vista visual del formulario de login
        Fortify::loginView(function () {
            return view('auth.login');
        });

        // Validacion personalizada de autenticacion con comprobacion de estatus activo
        Fortify::authenticateUsing(function (Request $request) {
            $campoIdentificador = Fortify::username();
            $credencial = $request->input($campoIdentificador);
            $password = $request->input('password');

            // Busqueda del modelo de usuario por correo electronico
            $user = User::where($campoIdentificador, $credencial)->first();

            if ($user && Hash::check($password, $user->password)) {
                // Restriccion estricta: solo usuarios con estatus activo pueden ingresar al sistema
                if (isset($user->estatus) && $user->estatus !== 'activo') {
                    throw ValidationException::withMessages([
                        $campoIdentificador => ['Su cuenta no se encuentra activa en el sistema. Contacte a la administracion.'],
                    ]);
                }

                // Registro del momento exacto del inicio de actividad para la auditoria de timeout
                session(['last_activity_time' => now()->timestamp]);

                return $user;
            }

            return null;
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
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
