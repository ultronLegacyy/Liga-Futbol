<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\RNF26Password;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

// Recuperación de contraseña por correo (HU-003). Reemplaza el flujo de Fortify porque este no aplica
// RNF-26, no cierra las sesiones abiertas, responde en inglés y revela qué correos están registrados
class RecuperacionContrasenaController extends Controller
{
    // La misma respuesta para cualquier caso, para no revelar qué correos tienen cuenta
    private const MENSAJE_SOLICITUD = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña en unos minutos.';

    /**
     * Muestra el formulario para solicitar el enlace de recuperación.
     */
    public function solicitud(): View
    {
        return view('auth.olvide-contrasena');
    }

    /**
     * Envía el enlace de un solo uso si el correo está registrado (CA1).
     */
    public function enviarEnlace(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Escribe un correo electrónico válido.',
        ]);

        try {
            // Correo inexistente o solicitud repetida antes de 60 segundos: el broker no envía nada
            Password::broker()->sendResetLink(['email' => $this->normalizarCorreo($request->input('email'))]);
        } catch (Throwable $e) {
            // Solo el detalle del fallo: el token y el enlace no se registran
            Log::error('Falló el envío del enlace de recuperación de contraseña.', ['exception' => $e]);
        }

        return back()->with('status', self::MENSAJE_SOLICITUD);
    }

    /**
     * Muestra el formulario de nueva contraseña o, si el enlace no sirve, la pantalla de enlace vencido (CA2).
     */
    public function formulario(Request $request, string $token): View|Response
    {
        $correo = $this->normalizarCorreo($request->query('email'));
        $usuario = $this->buscarUsuario($correo);

        if (! $usuario || ! Password::broker()->tokenExists($usuario, $token)) {
            return $this->enlaceVencido();
        }

        return view('auth.restablecer-contrasena', [
            'token' => $token,
            'email' => $correo,
        ]);
    }

    /**
     * Guarda la nueva contraseña con las reglas de RNF-26 y cierra las sesiones abiertas del titular (CA3).
     */
    public function restablecer(Request $request): RedirectResponse|Response
    {
        $correo = $this->normalizarCorreo($request->input('email'));

        // bail y string evitan que un arreglo llegue a RNF26Password y provoque un error 500
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['bail', 'required', 'string', 'confirmed', new RNF26Password($this->buscarUsuario($correo))],
        ], [
            'token.required' => 'El enlace de recuperación no es válido.',
            'token.string' => 'El enlace de recuperación no es válido.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.string' => 'La nueva contraseña no es válida.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $estado = Password::broker()->reset([
            'email' => $correo,
            'token' => (string) $request->input('token'),
            'password' => (string) $request->input('password'),
        ], function (User $usuario, string $clave): void {
            app(AuditService::class)->conMotivo('Recuperación de contraseña por correo', function () use ($usuario, $clave): void {
                // Un solo guardado: una sola fila de auditoría. La contraseña temporal deja de valer
                $usuario->forceFill([
                    'password' => Hash::make($clave),
                    'remember_token' => Str::random(60),
                    'requires_password_change' => false,
                ])->save();

                // Cierra las sesiones abiertas del titular (CA3)
                DB::table((string) config('session.table'))->where('user_id', $usuario->id)->delete();
            });

            event(new PasswordReset($usuario));
        });

        if ($estado === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');
        }

        // Enlace vencido, usado o alterado: no se indica si el correo existe
        return $this->enlaceVencido();
    }

    private function enlaceVencido(): Response
    {
        return response()->view('auth.enlace-vencido', [], 410);
    }

    private function buscarUsuario(string $correo): ?User
    {
        return $correo === '' ? null : User::where('email', $correo)->first();
    }

    // Minúsculas, igual que Fortify con lowercase_usernames; un valor que no es texto se trata como vacío
    private function normalizarCorreo(mixed $valor): string
    {
        return is_string($valor) ? Str::lower(trim($valor)) : '';
    }
}
