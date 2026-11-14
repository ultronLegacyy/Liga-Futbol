<?php

namespace App\Http\Requests;

use App\Models\Partido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validación para cambio de estado del partido (HU-029 / RF-06.3 / SCRUM-197)
class CambiarEstadoPartidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partido = $this->route('partido');
        if (! $partido instanceof Partido) {
            return false;
        }

        return $this->user()?->can('cambiarEstado', $partido) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('estado') && ! $this->has('nuevo_estado')) {
            $this->merge(['nuevo_estado' => $this->input('estado')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $estadosValidos = [
            Partido::ESTADO_BORRADOR,
            Partido::ESTADO_PROGRAMADO,
            Partido::ESTADO_CONFIRMADO,
            Partido::ESTADO_EN_JUEGO,
            Partido::ESTADO_FINALIZADO,
            Partido::ESTADO_SUSPENDIDO,
            Partido::ESTADO_CANCELADO,
            Partido::ESTADO_REPROGRAMADO,
        ];

        return [
            'nuevo_estado' => ['bail', 'required', 'string', Rule::in($estadosValidos)],
            'motivo' => [
                'bail',
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nuevo_estado.required' => 'Debe seleccionar el nuevo estado del partido.',
            'nuevo_estado.in' => 'El estado seleccionado no es válido en el sistema.',
            'motivo.required' => 'El motivo es obligatorio en todo cambio de estado.',
            'motivo.min' => 'El motivo debe contener al menos 10 caracteres descriptivos.',
            'motivo.max' => 'El motivo no debe exceder los 1000 caracteres.',
        ];
    }
}
