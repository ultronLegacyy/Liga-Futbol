<?php

namespace App\Http\Requests;

use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJugadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipo = $this->route('equipo');

        return $equipo instanceof Equipo
            && ($this->user()?->can('addPlayer', $equipo) ?? false);
    }

    public function rules(): array
    {
        return [
            'persona_id' => ['required', 'integer', Rule::exists('personas', 'id')],
            'dorsal' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'persona_id.required' => 'El jugador es obligatorio.',
            'persona_id.exists' => 'El jugador seleccionado no existe.',
            'dorsal.integer' => 'El dorsal debe ser un número entero.',
            'dorsal.min' => 'El dorsal debe ser al menos :min.',
            'dorsal.max' => 'El dorsal no debe ser mayor que :max.',
        ];
    }
}
