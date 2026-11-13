<?php

namespace App\Http\Requests;

use App\Models\Partido;
use Carbon\Carbon;
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
        $jornada = $partido instanceof Partido ? $partido->jornada : null;
        $grupoId = $jornada?->grupo_id;
        $fechaInicio = $jornada && $jornada->fecha_inicio
            ? Carbon::parse($jornada->fecha_inicio)->toDateString()
            : null;
        $fechaFin = $jornada && $jornada->fecha_fin
            ? Carbon::parse($jornada->fecha_fin)->toDateString()
            : null;

        $fechaRules = ['bail', 'nullable', 'date_format:Y-m-d'];
        if ($fechaInicio !== null) {
            $fechaRules[] = 'after_or_equal:'.$fechaInicio;
        }
        if ($fechaFin !== null) {
            $fechaRules[] = 'before_or_equal:'.$fechaFin;
        }

        return [
            'equipo_local_id' => [
                'bail',
                'sometimes',
                'required',
                'integer',
                'different:equipo_visitante_id',
                Rule::exists('equipos', 'id')->where('grupo_id', $grupoId),
            ],
            'equipo_visitante_id' => [
                'bail',
                'sometimes',
                'required',
                'integer',
                'different:equipo_local_id',
                Rule::exists('equipos', 'id')->where('grupo_id', $grupoId),
            ],
            'cancha_id' => ['bail', 'nullable', 'integer', Rule::exists('canchas', 'id')],
            'arbitro_id' => ['bail', 'nullable', 'integer', Rule::exists('arbitros', 'id')],
            'fecha_partido' => $fechaRules,
            'hora_partido' => ['bail', 'nullable', 'date_format:H:i,H:i:s'],
            'motivo' => ['bail', 'nullable', 'string', 'min:10', 'max:500'],
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
            'fecha_partido.date_format' => 'La fecha del partido debe tener el formato YYYY-MM-DD.',
            'fecha_partido.after_or_equal' => 'La fecha del partido no puede ser anterior al inicio de la jornada.',
            'fecha_partido.before_or_equal' => 'La fecha del partido no puede ser posterior al fin de la jornada.',
            'hora_partido.date_format' => 'La hora del partido debe tener el formato HH:MM.',
            'motivo.min' => 'El motivo de reprogramación debe contener al menos 10 caracteres explicativos.',
            'motivo.max' => 'El motivo no puede exceder los 500 caracteres.',
        ];
    }
}
