<?php

namespace App\Http\Requests;

use App\Models\Grupo;
use App\Models\Jornada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validación de alta de jornadas (HU-027, HU-028)
class StoreJornadaRequest extends FormRequest
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
        $grupo = $this->route('grupo');
        $grupoId = $grupo instanceof Grupo ? $grupo->id : $grupo;

        return [
            'numero_jornada' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('jornadas', 'numero_jornada')->where('grupo_id', $grupoId),
            ],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'equipo_descanso_id' => [
                'nullable',
                'integer',
                Rule::exists('equipos', 'id')->where('grupo_id', $grupoId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_jornada.required' => 'El número de jornada es obligatorio.',
            'numero_jornada.integer' => 'El número de jornada debe ser un número entero.',
            'numero_jornada.min' => 'El número de jornada debe ser al menos 1.',
            'numero_jornada.unique' => 'Ya existe una jornada con ese número en este grupo.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'equipo_descanso_id.exists' => 'El equipo en descanso debe pertenecer al grupo de la jornada.',
        ];
    }
}
