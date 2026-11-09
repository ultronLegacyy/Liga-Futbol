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

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // No validar existencia del CURP aquí: el endpoint responde igual exista o no.
        return ['curp' => ['required', 'string', 'size:18', 'regex:/^[A-Z]{4}[0-9]{6}[HMX][A-Z]{5}[A-Z0-9][0-9]$/i']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'curp.required' => 'Captura tu CURP.',
            'curp.string' => 'La CURP no tiene un formato válido.',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres.',
            'curp.regex' => 'La CURP no tiene un formato válido.',
        ];
    }
}
