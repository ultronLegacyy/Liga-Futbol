<?php

namespace App\Http\Requests\Cedula;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// HU-075: anular un gol, una tarjeta o una incidencia mal capturados, con motivo
class AnularRegistroRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $partido = $this->route('partido');

        return $partido instanceof Partido
            ? Gate::forUser($this->user())->inspect('capturar', [CedulaArbitral::class, $partido])
            : Response::deny('El partido no existe.');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['motivo' => ['bail', 'required', 'string', 'min:5', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe el motivo de la anulación.',
            'motivo.string' => 'El motivo de la anulación no es válido.',
            'motivo.min' => 'El motivo de la anulación debe tener al menos :min caracteres.',
            'motivo.max' => 'El motivo de la anulación admite hasta :max caracteres.',
        ];
    }

    public function motivo(): string
    {
        return (string) $this->validated('motivo');
    }
}
