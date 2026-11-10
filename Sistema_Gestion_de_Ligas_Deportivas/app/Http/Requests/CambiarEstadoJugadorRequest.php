<?php

namespace App\Http\Requests;

use App\Models\Jugador;
use App\Services\GestionJugadorService;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** HU-018: cambio de estado del jugador con motivo obligatorio. */
class CambiarEstadoJugadorRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $jugador = $this->route('jugador');

        return $jugador instanceof Jugador
            ? Gate::forUser($this->user())->inspect('changeStatus', $jugador)
            : Response::deny('El jugador no existe.');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'estado_elegibilidad' => ['bail', 'required', 'string', Rule::in(Jugador::ESTADOS_ELEGIBILIDAD)],
            'motivo' => ['bail', 'required', 'string', 'min:3', 'max:1000'],
            'fecha_baja' => [
                'bail',
                'nullable',
                Rule::requiredIf($this->input('estado_elegibilidad') === 'Baja'),
                'prohibited_unless:estado_elegibilidad,Baja',
                'date_format:Y-m-d',
                // La fecha mínima (inicio de la temporada) la valida GestionJugadorService::aplicarBaja().
                'before_or_equal:'.GestionJugadorService::hoy(),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'estado_elegibilidad.required' => 'Selecciona el nuevo estado del jugador.',
            'estado_elegibilidad.string' => 'El estado seleccionado no es válido.',
            'estado_elegibilidad.in' => 'El estado seleccionado no está permitido.',
            'motivo.required' => 'El motivo del cambio de estado es obligatorio.',
            'motivo.string' => 'El motivo del cambio de estado no es válido.',
            'motivo.min' => 'El motivo debe contener al menos :min caracteres.',
            'motivo.max' => 'El motivo no puede exceder :max caracteres.',
            'fecha_baja.required' => 'La fecha de baja es obligatoria cuando el nuevo estado es Baja.',
            'fecha_baja.prohibited_unless' => 'La fecha de baja solo se captura al cambiar al estado Baja.',
            'fecha_baja.date_format' => 'La fecha de baja debe tener el formato AAAA-MM-DD.',
            'fecha_baja.before_or_equal' => 'La fecha de baja no puede ser posterior a hoy.',
        ];
    }
}
