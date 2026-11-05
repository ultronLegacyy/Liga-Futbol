<?php

namespace App\Http\Requests;

use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipo = $this->route('equipo');

        return $equipo instanceof Equipo
            && ($this->user()?->can('update', $equipo) ?? false);
    }

    public function rules(): array
    {
        /** @var Equipo $equipo */
        $equipo = $this->route('equipo');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('equipos', 'nombre')
                    ->where('club_id', $equipo->club_id)
                    ->where('temporada_id', $equipo->temporada_id)
                    ->ignore($equipo),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del equipo es obligatorio.',
            'nombre.string' => 'El nombre del equipo debe ser texto.',
            'nombre.max' => 'El nombre del equipo no debe tener más de :max caracteres.',
            'nombre.unique' => 'El club ya tiene un equipo con ese nombre en la temporada.',
        ];
    }
}
