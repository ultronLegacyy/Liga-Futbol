<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Http\Requests\Concerns\ValidaOpcionesHorarios;
use App\Models\Grupo;
use App\Models\Jornada;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

// Solicitud de configuración y previsualización de rol de juegos (HU-032, RF-07.1, HU-074)
class GenerarRolRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;
    use ValidaOpcionesHorarios;

    /**
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return ['fecha_inicio', 'horarios_texto', 'horarios.*'];
    }
    public function authorize(): Response
    {
        $grupo = $this->route('grupo');

        return Gate::inspect('create', [Jornada::class, $grupo]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $grupo = $this->route('grupo');
        $fechaRules = ['required', 'date_format:Y-m-d'];

        if ($grupo instanceof Grupo && $grupo->temporada) {
            $temporada = $grupo->temporada;
            if ($temporada->fecha_inicio) {
                $fechaRules[] = 'after_or_equal:'.$temporada->fecha_inicio;
            }
            if ($temporada->fecha_fin) {
                $fechaRules[] = 'before_or_equal:'.$temporada->fecha_fin;
            }
        }

        return array_merge([
            'fecha_inicio' => array_merge(['bail'], $fechaRules),
            'intervalo_dias' => ['bail', 'required', 'integer', 'min:1', 'max:60'],
            'dias_duracion' => ['bail', 'required', 'integer', 'min:0', 'max:30'],
            'vueltas' => ['bail', 'required', 'integer', 'in:1,2'],
            'aleatorizar' => ['bail', 'nullable', 'boolean'],
            'sobrescribir' => ['bail', 'nullable', 'boolean'],
            'asignar_horarios' => ['bail', 'nullable', 'boolean'],
        ], $this->reglasOpcionesHorarios());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarHorariosTexto($validator);
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria para calcular el calendario.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato AAAA-MM-DD (Y-m-d).',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior al inicio de la temporada.',
            'fecha_inicio.before_or_equal' => 'La fecha de inicio no puede ser posterior al término de la temporada.',
            'intervalo_dias.required' => 'El intervalo de días entre jornadas es obligatorio.',
            'intervalo_dias.integer' => 'El intervalo debe ser un número entero.',
            'intervalo_dias.min' => 'El intervalo debe ser de al menos 1 día.',
            'intervalo_dias.max' => 'El intervalo no puede superar los 60 días.',
            'dias_duracion.required' => 'La duración de cada jornada en días es obligatoria.',
            'dias_duracion.integer' => 'La duración debe ser un número entero.',
            'dias_duracion.min' => 'La duración no puede ser negativa.',
            'dias_duracion.max' => 'La duración no puede superar los 30 días.',
            'vueltas.required' => 'Debe seleccionar el número de vueltas a programar.',
            'vueltas.integer' => 'El número de vueltas debe ser un entero válido.',
            'vueltas.in' => 'El número de vueltas debe ser 1 (ida) o 2 (ida y vuelta).',
            'aleatorizar.boolean' => 'La opción de sorteo aleatorio debe ser verdadera o falsa.',
            'sobrescribir.boolean' => 'La confirmación de reemplazo debe ser verdadera o falsa.',
            'asignar_horarios.boolean' => 'La opción de asignación automática de horarios debe ser verdadera o falsa.',
        ], $this->mensajesOpcionesHorarios());
    }
}
