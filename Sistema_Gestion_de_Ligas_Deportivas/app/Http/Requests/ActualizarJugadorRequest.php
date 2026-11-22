<?php

namespace App\Http\Requests;

use App\Models\Jugador;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** HU-022 CA2: cambio de los datos del jugador con motivo obligatorio que queda en su historial. */
class ActualizarJugadorRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $jugador = $this->route('jugador');

        return $jugador instanceof Jugador
            ? Gate::forUser($this->user())->inspect('update', $jugador)
            : Response::deny('El jugador no existe.');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'dorsal' => ['bail', 'sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'motivo' => ['bail', 'required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dorsal.integer' => 'El dorsal debe ser un número entero.',
            'dorsal.min' => 'El dorsal debe ser al menos :min.',
            'dorsal.max' => 'El dorsal no puede ser mayor que :max.',
            'motivo.required' => 'Escribe el motivo del cambio; quedará en el historial del jugador.',
            'motivo.string' => 'El motivo del cambio no es válido.',
            'motivo.min' => 'El motivo debe tener al menos :min caracteres.',
            'motivo.max' => 'El motivo no debe superar los :max caracteres.',
        ];
    }
}
