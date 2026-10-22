<?php

namespace App\Http\Controllers;

use App\Rules\RNF26Password;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ForcePasswordChangeController extends Controller
{
    /**
     * Muestra el formulario de cambio de contraseña obligatorio.
     */
    public function show()
    {
        return view('auth.force-password-change');
    }

    /**
     * Procesa la nueva contraseña y libera el acceso a la cuenta.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Aplicamos la regla RNF-26 pasándole el usuario para evaluar el 2FA
        $request->validate([
            'password' => ['required', 'confirmed', new RNF26Password($user)],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        // Actualizamos las contraseñas y quitamos la bandera de obligatoriedad
        $user->update([
            'password' => Hash::make($request->password),
            'password_hash' => Hash::make($request->password), // Actualizamos ambos campos según tu esquema
            'requires_password_change' => false,
        ]);

        // Redirigir al panel principal o dashboard
        return redirect('/dashboard')->with('status', 'Contraseña actualizada correctamente. ¡Bienvenido!');
    }
}