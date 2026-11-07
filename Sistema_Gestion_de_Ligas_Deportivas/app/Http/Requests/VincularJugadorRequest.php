<?php

namespace App\Http\Requests;

use App\Models\Jugador;
use Illuminate\Foundation\Http\FormRequest;

class VincularJugadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('link', Jugador::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $curp = $this->input('curp');

        if (is_string($curp)) {
            $this->merge(['curp' => strtoupper(trim($curp))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'curp' => ['required', 'string', 'alpha_num:ascii', 'size:18'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'curp.required' => 'La CURP es obligatoria.',
            'curp.alpha_num' => 'La CURP debe contener solo letras y números.',
            'curp.size' => 'La CURP debe tener 18 caracteres.',
        ];
    }
}
