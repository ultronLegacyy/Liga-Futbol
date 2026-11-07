<?php

namespace App\Http\Requests;

use App\Models\Jornada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validación de modificación de jornadas y descansos (HU-027, HU-028)
class UpdateJornadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jornada = $this->route('jornada');

        return $this->user()?->can('update', $jornada) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $jornada = $this->route('jornada');
        $jornadaId = $jornada instanceof Jornada ? $jornada->id : $jornada;
        $grupoId = $jornada instanceof Jornada ? $jornada->grupo_id : null;

        return [
            'numero_jornada' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('jornadas', 'numero_jornada')
                    ->where('grupo_id', $grupoId)
                    ->ignore($jornadaId),
            ],
            'fecha_inicio' => ['sometimes', 'required', 'date'],
            'fecha_fin' => ['sometimes', 'required', 'date', 'after_or_equal:fecha_inicio'],
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
            'numero_jornada.unique' => 'Ya existe otra jornada con ese número en este grupo.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'equipo_descanso_id.exists' => 'El equipo en descanso debe pertenecer al grupo de la jornada.',
        ];
    }
}
