<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAudienciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('disciplina.sancionar');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fecha_audiencia' => ['required', 'date'],
            'lugar_o_medio' => ['required', 'string', 'min:3', 'max:150'],
            'comparecientes' => ['required', 'string', 'min:5', 'max:1000'],
            'alegatos_defensa' => ['required', 'string', 'min:10', 'max:3000'],
            'resolucion' => ['nullable', 'string', 'max:3000'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_audiencia.required' => 'La fecha de la audiencia es obligatoria.',
            'fecha_audiencia.date' => 'La fecha de la audiencia no tiene un formato válido.',
            'lugar_o_medio.required' => 'El lugar o medio de la audiencia es obligatorio.',
            'lugar_o_medio.min' => 'El lugar o medio de la audiencia debe contener al menos 3 caracteres.',
            'comparecientes.required' => 'Debe registrar a los comparecientes presentes en la audiencia.',
            'comparecientes.min' => 'El listado de comparecientes debe contener al menos 5 caracteres.',
            'alegatos_defensa.required' => 'Los alegatos de defensa o descargos son obligatorios.',
            'alegatos_defensa.min' => 'Los alegatos de defensa deben contener al menos 10 caracteres.',
        ];
    }
}
