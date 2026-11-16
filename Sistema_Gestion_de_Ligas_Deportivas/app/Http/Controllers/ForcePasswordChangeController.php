<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        // 1. Actualización de la contraseña y remoción de la marca de cambio obligatorio
        $user->update([
            'password' => Hash::make($request->password),
            'requires_password_change' => false,
        ]);

        // 2. Redirección al destino correspondiente según el rol del usuario autenticado
        $destino = $user instanceof User ? $user->rutaInicio() : (string) config('fortify.home', '/');

        return redirect($destino)->with('status', 'Contraseña actualizada correctamente. ¡Bienvenido!');
    }
}
