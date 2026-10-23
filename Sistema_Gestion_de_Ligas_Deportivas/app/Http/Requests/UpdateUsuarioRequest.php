<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Recuperamos el modelo inyectado en la ruta para ignorar su propio email
        $usuarioId = $this->route('usuario')->id;

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:usuarios,email,' . $usuarioId,
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
            'motivo' => 'required|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo es inválido.',
            'email.unique' => 'Este correo ya está registrado en otra cuenta.',
            'roles.required' => 'Debes asignar al menos un rol.',
            'roles.*.exists' => 'El rol seleccionado no es válido.',
            'motivo.required' => 'El motivo es obligatorio para registrar el cambio.'
        ];
    }
}
