<?php

namespace App\Http\Requests;

use App\Models\Partido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validación de reprogramación de fecha, hora o cancha con motivo obligatorio (HU-029 / RF-06.4 / SCRUM-196, SCRUM-197)
class ReprogramarPartidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_partido' => ['required', 'date'],
            'hora_partido' => ['required', 'date_format:H:i'],
            'cancha_id' => ['nullable', 'exists:canchas,id'],
            'arbitro_id' => ['nullable', 'exists:arbitros,id'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
            'estado' => [
                'nullable',
                'string',
                Rule::in([
                    Partido::ESTADO_REPROGRAMADO,
                    Partido::ESTADO_PROGRAMADO,
                    Partido::ESTADO_CONFIRMADO,
                ]),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    /** @var Partido|null $partido */
                    $partido = $this->route('partido');
                    if (! $partido instanceof Partido) {
                        return;
                    }
                    $nuevoEstado = (string) $value;
                    if ($partido->estado === Partido::ESTADO_REPROGRAMADO && $nuevoEstado === Partido::ESTADO_REPROGRAMADO) {
                        return;
                    }
                    if (! $partido->puedeTransicionarA($nuevoEstado)) {
                        $fail("La transición del estado '{$partido->estado}' a '{$nuevoEstado}' no está permitida por el flujo del sistema.");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_partido.required' => 'La nueva fecha del partido es obligatoria para la reprogramación.',
            'fecha_partido.date' => 'La fecha ingresada debe tener un formato de fecha válido.',
            'hora_partido.required' => 'El nuevo horario del partido es obligatorio para la reprogramación.',
            'hora_partido.date_format' => 'El horario debe especificarse en formato de 24 horas (HH:MM).',
            'cancha_id.exists' => 'La cancha seleccionada no existe en el catálogo.',
            'arbitro_id.exists' => 'El árbitro seleccionado no existe en el sistema.',
            'motivo.required' => 'El motivo de la reprogramación es obligatorio (HU-029 Criterio 2).',
            'motivo.min' => 'El motivo de reprogramación debe contener al menos 10 caracteres explicativos.',
            'motivo.max' => 'El motivo no debe superar los 1000 caracteres.',
        ];
    }
}
