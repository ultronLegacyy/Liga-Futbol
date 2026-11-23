<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\ExpedienteDisciplinario;
use App\Models\ExpedienteInvolucrado;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DictaminarSancionRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto {
        prepareForValidation as protected descartarArreglos;
    }

    public function authorize(): Response
    {
        $usuario = $this->user();
        if (! $usuario instanceof User) {
            return Response::deny('Debe iniciar sesión para dictaminar sanciones disciplinarias.');
        }

        return Gate::forUser($usuario)->inspect('create', Sancion::class);
    }

    /**
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return [
            'persona_id',
            'equipo_id',
            'articulo_id',
            'tipo_sancion',
            'partidos_suspendidos',
            'fecha_inicio',
            'fecha_fin',
            'monto_multa',
            'descripcion_sancion',
            'motivo_resolucion',
            'validar_inmediatamente',
            'publicar_en_boletin',
            'cerrar_expediente',
            'motivo',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->descartarArreglos();

        $tipo = $this->input('tipo_sancion');
        if (is_string($tipo)) {
            $map = [
                'Suspension_Partidos' => Sancion::TIPO_PARTIDOS,
                'Suspension_Temporal' => Sancion::TIPO_TEMPORAL,
                'Multa_Economica' => Sancion::TIPO_MULTA,
                'Amonestacion' => Sancion::TIPO_AMONESTACION,
            ];
            $normalizado = $map[$tipo] ?? strtolower(trim($tipo));
            $this->merge(['tipo_sancion' => $normalizado]);
        }

        if (! $this->filled('descripcion_sancion') && $this->filled('motivo_resolucion') && is_string($this->input('motivo_resolucion'))) {
            $this->merge(['descripcion_sancion' => $this->input('motivo_resolucion')]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $expediente = $this->route('expediente');
        $expedienteId = $expediente instanceof ExpedienteDisciplinario ? $expediente->id : 0;

        $equiposPermitidos = [];
        if ($expediente instanceof ExpedienteDisciplinario) {
            $partido = $expediente->partido;
            if ($partido !== null) {
                $equiposPermitidos = array_values(array_filter([
                    $partido->equipo_local_id,
                    $partido->equipo_visitante_id,
                ]));
            } else {
                $equiposPermitidos = ExpedienteInvolucrado::query()
                    ->where('expediente_id', $expediente->id)
                    ->whereNotNull('equipo_id')
                    ->pluck('equipo_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return [
            'persona_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('expediente_involucrados', 'persona_id')->where('expediente_id', $expedienteId),
            ],
            'equipo_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::in($equiposPermitidos),
            ],
            'articulo_id' => ['bail', 'nullable', 'integer', 'exists:catalogo_articulos,id'],
            'tipo_sancion' => ['bail', 'required', 'string', Rule::in(Sancion::TIPOS_PERMITIDOS)],
            'partidos_suspendidos' => ['bail', 'nullable', 'integer', 'min:0', 'max:100'],
            'fecha_inicio' => ['bail', 'nullable', 'string', 'date_format:Y-m-d'],
            'fecha_fin' => [
                'bail',
                'nullable',
                'string',
                'date_format:Y-m-d',
                'after_or_equal:fecha_inicio',
                'required_if:tipo_sancion,'.Sancion::TIPO_TEMPORAL,
            ],
            'monto_multa' => [
                'bail',
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],
            'descripcion_sancion' => ['bail', 'nullable', 'string', 'min:5', 'max:1000'],
            'motivo_resolucion' => ['bail', 'required', 'string', 'min:10', 'max:2000'],
            'validar_inmediatamente' => ['bail', 'nullable', 'boolean'],
            'publicar_en_boletin' => ['bail', 'nullable', 'boolean'],
            'cerrar_expediente' => ['bail', 'nullable', 'boolean'],
            'motivo' => ['bail', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $tipo = is_string($this->input('tipo_sancion')) ? $this->input('tipo_sancion') : '';

            if (! $this->filled('persona_id') && ! $this->filled('equipo_id')) {
                $v->errors()->add('persona_id', 'Debe seleccionar al menos una persona involucrada o un equipo del expediente.');
            }

            if (in_array($tipo, Sancion::TIPOS_INHABILITANTES, true) && ! $this->filled('persona_id')) {
                $v->errors()->add('persona_id', 'Las suspensiones deportivas por partidos o por tiempo requieren seleccionar a una persona involucrada en el expediente.');
            }

            if ($tipo === Sancion::TIPO_PARTIDOS && (int) $this->input('partidos_suspendidos', 0) <= 0) {
                $v->errors()->add('partidos_suspendidos', 'Para una suspensión por partidos debe indicar al menos 1 partido.');
            }

            if ($tipo === Sancion::TIPO_MULTA) {
                $monto = $this->input('monto_multa');
                if (! is_numeric($monto) || (float) $monto <= 0) {
                    $v->errors()->add('monto_multa', 'Una multa económica requiere especificar un monto mayor a cero.');
                }
            }

            if ($this->boolean('publicar_en_boletin') && ! $this->boolean('validar_inmediatamente')) {
                $v->errors()->add('publicar_en_boletin', 'RNF-44: No se puede publicar una sanción en el boletín sin validarla formalmente en el dictamen.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'persona_id.integer' => 'El identificador de la persona debe ser un número entero válido.',
            'persona_id.exists' => 'La persona seleccionada no forma parte de los involucrados de este expediente disciplinario.',
            'equipo_id.integer' => 'El identificador del equipo debe ser un número entero válido.',
            'equipo_id.in' => 'El equipo seleccionado no corresponde al partido ni a los involucrados de este expediente.',
            'articulo_id.integer' => 'El identificador del artículo reglamentario debe ser un número entero válido.',
            'articulo_id.exists' => 'El artículo reglamentario seleccionado no existe en el catálogo.',
            'tipo_sancion.required' => 'El tipo de sanción es obligatorio.',
            'tipo_sancion.string' => 'El tipo de sanción debe ser una cadena de texto válida.',
            'tipo_sancion.in' => 'El tipo de sanción seleccionado no es válido para el reglamento vigente.',
            'partidos_suspendidos.integer' => 'Los partidos de suspensión deben ser un número entero.',
            'partidos_suspendidos.min' => 'Los partidos de suspensión no pueden ser negativos.',
            'partidos_suspendidos.max' => 'Los partidos de suspensión no pueden exceder de 100.',
            'fecha_inicio.string' => 'La fecha de inicio debe ser una cadena de texto válida.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato válido (YYYY-MM-DD).',
            'fecha_fin.string' => 'La fecha de término debe ser una cadena de texto válida.',
            'fecha_fin.date_format' => 'La fecha de término debe tener el formato válido (YYYY-MM-DD).',
            'fecha_fin.after_or_equal' => 'La fecha de término debe ser igual o posterior a la fecha de inicio.',
            'fecha_fin.required_if' => 'La fecha de término es obligatoria para una suspensión temporal.',
            'monto_multa.numeric' => 'El monto de la multa debe ser un valor numérico válido.',
            'monto_multa.min' => 'El monto de la multa no puede ser negativo.',
            'monto_multa.max' => 'El monto de la multa no puede exceder de 99,999,999.99.',
            'monto_multa.decimal' => 'El monto de la multa puede contener como máximo 2 decimales.',
            'descripcion_sancion.string' => 'La descripción de la sanción debe ser texto válido.',
            'descripcion_sancion.min' => 'La descripción de la sanción debe tener al menos 5 caracteres.',
            'descripcion_sancion.max' => 'La descripción de la sanción no debe superar los 1000 caracteres.',
            'motivo_resolucion.required' => 'La fundamentación jurídico-deportiva de la resolución es obligatoria.',
            'motivo_resolucion.string' => 'La fundamentación de la resolución debe ser texto válido.',
            'motivo_resolucion.min' => 'El motivo o fundamentación de la resolución debe tener al menos 10 caracteres.',
            'motivo_resolucion.max' => 'La fundamentación de la resolución no debe superar los 2000 caracteres.',
            'validar_inmediatamente.boolean' => 'El indicador de validación inmediata debe ser verdadero o falso.',
            'publicar_en_boletin.boolean' => 'El indicador de publicación en boletín debe ser verdadero o falso.',
            'cerrar_expediente.boolean' => 'El indicador de cierre de expediente debe ser verdadero o falso.',
            'motivo.string' => 'El motivo de auditoría debe ser texto válido.',
            'motivo.max' => 'El motivo de auditoría no debe superar los 500 caracteres.',
        ];
    }
}
