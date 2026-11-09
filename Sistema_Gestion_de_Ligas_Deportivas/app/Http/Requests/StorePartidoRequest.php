<?php

namespace App\Http\Requests;

use App\Models\Jornada;
use App\Models\Partido;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Validación de alta manual de partidos (HU-027, RF-06.1)
class StorePartidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jornada = $this->route('jornada');

        return $this->user()?->can('create', [Partido::class, $jornada]) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $jornada = $this->route('jornada');
        $grupoId = $jornada instanceof Jornada ? $jornada->grupo_id : null;
        $fechaInicio = $jornada instanceof Jornada && $jornada->fecha_inicio
            ? Carbon::parse($jornada->fecha_inicio)->toDateString()
            : null;
        $fechaFin = $jornada instanceof Jornada && $jornada->fecha_fin
            ? Carbon::parse($jornada->fecha_fin)->toDateString()
            : null;

        $fechaRules = ['nullable', 'date', 'required_with:cancha_id,arbitro_id'];
        if ($fechaInicio !== null) {
            $fechaRules[] = 'after_or_equal:'.$fechaInicio;
        }
        if ($fechaFin !== null) {
            $fechaRules[] = 'before_or_equal:'.$fechaFin;
        }

        return [
            'equipo_local_id' => [
                'required',
                'integer',
                'different:equipo_visitante_id',
                Rule::exists('equipos', 'id')->where('grupo_id', $grupoId),
            ],
            'equipo_visitante_id' => [
                'required',
                'integer',
                'different:equipo_local_id',
                Rule::exists('equipos', 'id')->where('grupo_id', $grupoId),
            ],
            'cancha_id' => ['nullable', 'integer', Rule::exists('canchas', 'id')],
            'arbitro_id' => ['nullable', 'integer', Rule::exists('arbitros', 'id')],
            'fecha_partido' => $fechaRules,
            'hora_partido' => ['nullable', 'date_format:H:i,H:i:s', 'required_with:cancha_id,arbitro_id'],
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
            'fecha_partido.required_with' => 'La fecha del partido es obligatoria al asignar cancha o árbitro.',
            'fecha_partido.date' => 'La fecha del partido debe ser una fecha válida.',
            'fecha_partido.after_or_equal' => 'La fecha del partido no puede ser anterior al inicio de la jornada.',
            'fecha_partido.before_or_equal' => 'La fecha del partido no puede ser posterior al fin de la jornada.',
            'hora_partido.required_with' => 'La hora del partido es obligatoria al asignar cancha o árbitro.',
            'hora_partido.date_format' => 'La hora del partido debe tener el formato HH:MM.',
            'estado.in' => 'El estado del partido no es válido.',
        ];
    }
}
