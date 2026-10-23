<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la manejaremos en el controlador con la Policy
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:usuarios',
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
            'email.unique' => 'Este correo ya está registrado en el sistema.',
            'roles.required' => 'Debes asignar al menos un rol al usuario.',
            'roles.*.exists' => 'El rol seleccionado no es válido.',
            'motivo.required' => 'El motivo es obligatorio para el registro de auditoría.'
        ];
    }
}
