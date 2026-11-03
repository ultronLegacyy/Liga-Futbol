<?php

namespace App\Http\Requests;

use App\Models\Equipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Equipo::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $clubId = $this->user()?->club_id;

        return [
            'temporada_id' => ['bail', 'required', 'integer', Rule::exists('temporadas', 'id')->where('estado', 'activa')],
            'categoria_id' => ['bail', 'required', 'integer', Rule::exists('categorias', 'id')->where('temporada_id', $this->input('temporada_id'))],
            'grupo_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('grupos', 'id')
                    ->where('temporada_id', $this->input('temporada_id'))
                    ->where('categoria_id', $this->input('categoria_id')),
            ],
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('equipos', 'nombre')
                    ->where('club_id', $clubId)
                    ->where('temporada_id', $this->input('temporada_id')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'temporada_id.required' => 'La temporada es obligatoria.',
            'temporada_id.exists' => 'La temporada seleccionada no está activa.',
            'categoria_id.required' => 'La categoría es obligatoria.',
            'categoria_id.exists' => 'La categoría no pertenece a la temporada seleccionada.',
            'grupo_id.required' => 'El grupo es obligatorio.',
            'grupo_id.exists' => 'El grupo no pertenece a la categoría y temporada seleccionadas.',
            'nombre.required' => 'El nombre del equipo es obligatorio.',
            'nombre.string' => 'El nombre del equipo debe ser texto.',
            'nombre.max' => 'El nombre del equipo no debe tener más de :max caracteres.',
            'nombre.unique' => 'El club ya tiene un equipo con ese nombre en la temporada seleccionada.',
        ];
    }
}
