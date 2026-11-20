<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicarBoletinRequest extends FormRequest
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
            'sanciones_ids' => ['required', 'array', 'min:1'],
            'sanciones_ids.*' => ['integer', 'exists:sanciones,id'],
            'autorizado_por_cargo' => ['nullable', 'string', 'max:150'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sanciones_ids.required' => 'Debe seleccionar al menos una sanción para publicar en el boletín.',
            'sanciones_ids.min' => 'Debe seleccionar al menos una sanción.',
            'motivo.required' => 'Debe registrar el motivo de la publicación oficial del boletín.',
            'motivo.min' => 'El motivo de la publicación debe contener al menos 5 caracteres.',
        ];
    }
}
