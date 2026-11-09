<?php

namespace App\Http\Requests;

use App\Models\Jornada;
use Illuminate\Foundation\Http\FormRequest;

// Confirmación y persistencia del rol regular generado (HU-032, RF-07.1)
class ConfirmarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grupo = $this->route('grupo');

        return $this->user()?->can('create', [Jornada::class, $grupo]) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fixture_payload' => ['required', 'string'],
            'sobrescribir' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fixture_payload.required' => 'La propuesta de enfrentamientos es requerida para su confirmación.',
            'fixture_payload.string' => 'El formato de la propuesta de enfrentamientos no es válido.',
        ];
    }
}
