<?php

namespace App\Http\Requests;

use App\Models\Partido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validación para modificar o reprogramar partidos (HU-027, RF-06.1)
class UpdatePartidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partido = $this->route('partido');

        return $this->user()?->can('update', $partido) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $partido = $this->route('partido');
        $grupoId = $partido instanceof Partido ? $partido->jornada->grupo_id : null;

        return [
            'equipo_local_id' => [
                'sometimes',
                'required',
                'integer',
                'different:equipo_visitante_id',
                Rule::exists('equipos', 'id')->where('grupo_id', $grupoId),
            ],
            'equipo_visitante_id' => [
                'sometimes',
                'required',
                'integer',
                'different:equipo_local_id',
                Rule::exists('equipos', 'id')->where('grupo_id', $grupoId),
            ],
            'cancha_id' => ['nullable', 'integer', Rule::exists('canchas', 'id')],
            'arbitro_id' => ['nullable', 'integer', Rule::exists('arbitros', 'id')],
            'fecha_partido' => ['nullable', 'date'],
            'hora_partido' => ['nullable', 'date_format:H:i,H:i:s'],
            'estado' => ['nullable', 'string', Rule::in(['borrador', 'programado', 'confirmado', 'en juego', 'finalizado', 'suspendido'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'equipo_local_id.required' => 'El equipo local es obligatorio.',
            'equipo_local_id.exists' => 'El equipo local debe pertenecer al grupo de la jornada.',
            'equipo_local_id.different' => 'El equipo local debe ser distinto al equipo visitante.',
            'equipo_visitante_id.required' => 'El equipo visitante es obligatorio.',
            'equipo_visitante_id.exists' => 'El equipo visitante debe pertenecer al grupo de la jornada.',
            'equipo_visitante_id.different' => 'El equipo visitante debe ser distinto al equipo local.',
            'cancha_id.exists' => 'La cancha seleccionada no es válida.',
            'arbitro_id.exists' => 'El árbitro seleccionado no es válido.',
            'fecha_partido.date' => 'La fecha del partido debe ser una fecha válida.',
            'hora_partido.date_format' => 'La hora del partido debe tener el formato HH:MM.',
            'estado.in' => 'El estado del partido no es válido.',
        ];
    }
}
