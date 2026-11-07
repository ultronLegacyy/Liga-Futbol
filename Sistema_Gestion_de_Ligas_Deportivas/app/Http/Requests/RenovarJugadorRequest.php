<?php

namespace App\Http\Requests;

use App\Models\Jugador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenovarJugadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jugador = $this->route('jugador');

        return $jugador instanceof Jugador
            && ($this->user()?->can('renew', $jugador) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'equipo_id' => ['required', 'integer', Rule::exists('equipos', 'id')],
            'dorsal' => [
                'nullable',
                'integer',
                'min:1',
                'max:99',
                Rule::unique('jugadores_equipos', 'dorsal')->where('equipo_id', $this->input('equipo_id')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'equipo_id.required' => 'El equipo de la temporada activa es obligatorio.',
            'equipo_id.exists' => 'El equipo seleccionado no existe.',
            'dorsal.integer' => 'El dorsal debe ser un número entero.',
            'dorsal.min' => 'El dorsal debe ser al menos :min.',
            'dorsal.max' => 'El dorsal no debe ser mayor que :max.',
            'dorsal.unique' => 'El dorsal ya está asignado a otro jugador de este equipo.',
        ];
    }
}
