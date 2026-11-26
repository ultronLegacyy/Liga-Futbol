<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// HU-082 (criterio 4): el Administrador General reinicia el segundo factor de otra cuenta, con motivo y su contraseña
class ReiniciarSegundoFactorRequest extends FormRequest
{
    // El formulario de edición de la cuenta también tiene «motivo»: los errores del reinicio van aparte
    protected $errorBag = 'segundoFactor';

    public function authorize(): Response
    {
        $usuario = $this->route('usuario');

        return Gate::forUser($this->user())->inspect('reiniciarSegundoFactor', $usuario instanceof User ? $usuario : User::class);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'motivo' => ['bail', 'required', 'string', 'min:3', 'max:255'],
            // confirmPassword del Administrador (decisión 2 de Daniel)
            'password' => ['bail', 'required', 'string', 'current_password:web'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe el motivo del reinicio del segundo factor.',
            'motivo.string' => 'El motivo debe ser texto.',
            'motivo.min' => 'El motivo debe tener al menos :min caracteres.',
            'motivo.max' => 'El motivo no debe tener más de :max caracteres.',
            'password.required' => 'Escribe tu contraseña para confirmar el reinicio.',
            'password.string' => 'La contraseña no es válida.',
            'password.current_password' => 'La contraseña no es correcta.',
        ];
    }
}
