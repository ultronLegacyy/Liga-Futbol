<?php

namespace App\Http\Requests;

use App\Models\Jugador;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** HU-017, criterio 2: renovación a la temporada activa conservando la identidad. */
class RenovarJugadorRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $jugador = $this->route('jugador');

        return $jugador instanceof Jugador
            ? Gate::forUser($this->user())->inspect('renew', $jugador)
            : Response::deny('El jugador no existe.');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'equipo_id' => ['bail', 'required', 'integer', Rule::exists('equipos', 'id')],
            'dorsal' => ['bail', 'nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'equipo_id.required' => 'Selecciona el equipo de la nueva temporada.',
            'equipo_id.integer' => 'El equipo seleccionado no es válido.',
            'equipo_id.exists' => 'El equipo seleccionado no existe.',
            'dorsal.integer' => 'El dorsal debe ser un número entero.',
            'dorsal.min' => 'El dorsal debe ser al menos :min.',
            'dorsal.max' => 'El dorsal no puede ser mayor que :max.',
        ];
    }
}
