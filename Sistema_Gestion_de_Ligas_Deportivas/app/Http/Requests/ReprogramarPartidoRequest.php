<?php

namespace App\Http\Requests;

use App\Models\Partido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Validación de reprogramación de fecha, hora o cancha con motivo obligatorio (HU-029 / RF-06.4 / SCRUM-196, SCRUM-197)
class ReprogramarPartidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partido = $this->route('partido');
        if (! $partido instanceof Partido) {
            return false;
        }

        return $this->user()?->can('reprogramar', $partido) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha_partido' => ['bail', 'required', 'date_format:Y-m-d'],
            'hora_partido' => ['bail', 'required', 'date_format:H:i,H:i:s'],
            'cancha_id' => ['bail', 'required', 'integer', 'exists:canchas,id'],
            'arbitro_id' => ['bail', 'nullable', 'integer', 'exists:arbitros,id'],
            'motivo' => ['bail', 'required', 'string', 'min:10', 'max:1000'],
            'estado' => [
                'bail',
                'nullable',
                'string',
                Rule::in([
                    Partido::ESTADO_REPROGRAMADO,
                    Partido::ESTADO_PROGRAMADO,
                    Partido::ESTADO_CONFIRMADO,
                ]),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }
                    /** @var Partido|null $partido */
                    $partido = $this->route('partido');
                    if (! $partido instanceof Partido) {
                        return;
                    }
                    $nuevoEstado = $value;
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
            'fecha_partido.date_format' => 'La fecha ingresada debe tener un formato de fecha válido (YYYY-MM-DD).',
            'hora_partido.required' => 'El nuevo horario del partido es obligatorio para la reprogramación.',
            'hora_partido.date_format' => 'El horario debe especificarse en formato de 24 horas (HH:MM).',
            'cancha_id.required' => 'La asignación de cancha es obligatoria para reprogramar el partido (HU-029).',
            'cancha_id.exists' => 'La cancha seleccionada no existe en el catálogo.',
            'arbitro_id.exists' => 'El árbitro seleccionado no existe en el sistema.',
            'motivo.required' => 'El motivo de la reprogramación es obligatorio (HU-029 Criterio 2).',
            'motivo.min' => 'El motivo de reprogramación debe contener al menos 10 caracteres explicativos.',
            'motivo.max' => 'El motivo no debe superar los 1000 caracteres.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var Partido|null $partido */
            $partido = $this->route('partido');
            if (! $partido instanceof Partido) {
                return;
            }

            if (in_array($partido->estado, [Partido::ESTADO_FINALIZADO, Partido::ESTADO_CANCELADO], true)) {
                $v->errors()->add('estado', 'Un partido finalizado o cancelado no puede ser reprogramado.');

                return;
            }

            if (in_array($partido->estado, [Partido::ESTADO_BORRADOR, Partido::ESTADO_EN_JUEGO], true)) {
                $v->errors()->add('estado', 'Un partido en borrador o en juego no puede ser reprogramado.');

                return;
            }

            $nuevoEstado = $this->input('estado');
            if ($nuevoEstado === null || $nuevoEstado === '') {
                $nuevoEstado = Partido::ESTADO_REPROGRAMADO;
            }

            if (! is_string($nuevoEstado)) {
                return;
            }

            if ($partido->estado === Partido::ESTADO_REPROGRAMADO && $nuevoEstado === Partido::ESTADO_REPROGRAMADO) {
                return;
            }

            if (! $partido->puedeTransicionarA($nuevoEstado)) {
                $v->errors()->add('estado', "La transición de estado '{$partido->estado}' a '{$nuevoEstado}' no está permitida por el flujo del sistema.");
            }
        });
    }
}
