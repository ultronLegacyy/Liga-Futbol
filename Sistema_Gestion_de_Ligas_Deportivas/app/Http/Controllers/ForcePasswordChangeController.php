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

        $request->validate([
            'password' => ['required', 'confirmed', new RNF26Password($user)],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        // 1. Quitamos la línea de password_hash para dejar una sola columna de contraseña
        $user->update([
            'password' => Hash::make($request->password),
            'requires_password_change' => false,
        ]);

        // 2. Corregimos la redirección usando la configuración de Fortify en lugar de la ruta manual quemada
        return redirect(config('fortify.home'))->with('status', 'Contraseña actualizada correctamente. ¡Bienvenido!');
    }
}
