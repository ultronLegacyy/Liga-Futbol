<?php

namespace App\Http\Requests;

use App\Models\Jornada;
use Illuminate\Foundation\Http\FormRequest;

// Solicitud de configuración y previsualización de rol de juegos (HU-032, RF-07.1)
class GenerarRolRequest extends FormRequest
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
            'fecha_inicio' => ['required', 'date'],
            'intervalo_dias' => ['required', 'integer', 'min:1', 'max:60'],
            'dias_duracion' => ['required', 'integer', 'min:0', 'max:30'],
            'vueltas' => ['required', 'integer', 'in:1,2'],
            'aleatorizar' => ['nullable', 'boolean'],
            'sobrescribir' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria para calcular el calendario.',
            'fecha_inicio.date' => 'La fecha de inicio debe tener un formato de fecha válido.',
            'intervalo_dias.required' => 'El intervalo de días entre jornadas es obligatorio.',
            'intervalo_dias.integer' => 'El intervalo debe ser un número entero.',
            'intervalo_dias.min' => 'El intervalo debe ser de al menos 1 día.',
            'intervalo_dias.max' => 'El intervalo no puede superar los 60 días.',
            'dias_duracion.required' => 'La duración de cada jornada en días es obligatoria.',
            'dias_duracion.integer' => 'La duración debe ser un número entero.',
            'dias_duracion.min' => 'La duración no puede ser negativa.',
            'dias_duracion.max' => 'La duración no puede superar los 30 días.',
            'vueltas.required' => 'Debe seleccionar el número de vueltas a programar.',
            'vueltas.in' => 'El número de vueltas debe ser 1 (ida) o 2 (ida y vuelta).',
        ];
    }
}
