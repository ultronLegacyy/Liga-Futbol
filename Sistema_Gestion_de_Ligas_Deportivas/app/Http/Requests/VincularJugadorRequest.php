<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VincularJugadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('link', \App\Models\Jugador::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // No validar existencia del CURP aquí: el endpoint responde igual exista o no.
        return ['curp' => ['required', 'string', 'size:18', 'regex:/^[A-Z0-9]+$/i']];
    }
}
