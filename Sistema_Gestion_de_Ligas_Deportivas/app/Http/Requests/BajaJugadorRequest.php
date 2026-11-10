<?php

namespace App\Http\Requests;

use App\Models\Jugador;
use App\Services\GestionJugadorService;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** HU-017, criterio 1: baja con motivo y fecha. */
class BajaJugadorRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $jugador = $this->route('jugador');

        return $jugador instanceof Jugador
            ? Gate::forUser($this->user())->inspect('deactivate', $jugador)
            : Response::deny('El jugador no existe.');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'motivo' => ['bail', 'required', 'string', 'min:3', 'max:1000'],
            // La fecha mínima (inicio de la temporada) la valida GestionJugadorService::aplicarBaja().
            'fecha_baja' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:'.GestionJugadorService::hoy()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de la baja es obligatorio.',
            'motivo.string' => 'El motivo de la baja no es válido.',
            'motivo.min' => 'El motivo de la baja debe tener al menos :min caracteres.',
            'motivo.max' => 'El motivo de la baja no puede exceder :max caracteres.',
            'fecha_baja.required' => 'La fecha de baja es obligatoria.',
            'fecha_baja.date_format' => 'La fecha de baja debe tener el formato AAAA-MM-DD.',
            'fecha_baja.before_or_equal' => 'La fecha de baja no puede ser posterior a hoy.',
        ];
    }
}
