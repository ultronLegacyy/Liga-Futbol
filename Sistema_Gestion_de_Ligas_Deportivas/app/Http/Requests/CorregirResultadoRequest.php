<?php

namespace App\Http\Requests;

use App\Models\CedulaArbitral;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** HU-039: corregir el marcador de un resultado validado, con motivo obligatorio. */
class CorregirResultadoRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $cedula = $this->route('cedula');

        return $cedula instanceof CedulaArbitral
            ? Gate::forUser($this->user())->inspect('corregirResultado', $cedula)
            : Response::deny('El resultado no existe.');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'goles_local' => ['bail', 'required', 'integer', 'min:0', 'max:99'],
            'goles_visitante' => ['bail', 'required', 'integer', 'min:0', 'max:99'],
            'motivo' => ['bail', 'required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'goles_local.required' => 'Escribe los goles del equipo local.',
            'goles_visitante.required' => 'Escribe los goles del equipo visitante.',
            'goles_local.integer' => 'Los goles deben ser un número entero.',
            'goles_visitante.integer' => 'Los goles deben ser un número entero.',
            'goles_local.min' => 'Los goles no pueden ser negativos.',
            'goles_visitante.min' => 'Los goles no pueden ser negativos.',
            'goles_local.max' => 'Los goles no pueden pasar de :max.',
            'goles_visitante.max' => 'Los goles no pueden pasar de :max.',
            'motivo.required' => 'Escribe el motivo de la corrección; quedará en la bitácora.',
            'motivo.string' => 'El motivo de la corrección no es válido.',
            'motivo.min' => 'El motivo debe tener al menos :min caracteres.',
            'motivo.max' => 'El motivo no debe superar los :max caracteres.',
        ];
    }
}
