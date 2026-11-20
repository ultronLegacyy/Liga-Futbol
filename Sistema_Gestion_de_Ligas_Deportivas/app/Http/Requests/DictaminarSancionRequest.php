<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DictaminarSancionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('disciplina.sancionar');
    }

    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo_sancion');
        if (is_string($tipo)) {
            $map = [
                'Suspension_Partidos' => 'partidos_suspension',
                'Suspension_Temporal' => 'suspension_temporal',
                'Inhabilitacion_Definitiva' => 'inhabilitacion',
                'Multa_Economica' => 'multa_economica',
                'Amonestacion' => 'amonestacion',
                'Suspension_Equipo' => 'suspension_equipo',
            ];
            $normalizado = $map[$tipo] ?? strtolower($tipo);
            $this->merge(['tipo_sancion' => $normalizado]);
        }

        if (! $this->has('partidos_suspendidos') && $this->has('partidos_suspension')) {
            $this->merge(['partidos_suspendidos' => $this->input('partidos_suspension')]);
        }

        if (! $this->filled('descripcion_sancion') && $this->filled('motivo_resolucion')) {
            $this->merge(['descripcion_sancion' => $this->input('motivo_resolucion')]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'persona_id' => ['nullable', 'integer', 'exists:personas,id'],
            'equipo_id' => ['nullable', 'integer', 'exists:equipos,id'],
            'articulo_id' => ['nullable', 'integer', 'exists:catalogo_articulos,id'],
            'tipo_sancion' => ['required', 'string', 'in:partidos_suspension,suspension_temporal,inhabilitacion,multa_economica,suspension_equipo,amonestacion'],
            'partidos_suspendidos' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'monto_multa' => ['nullable', 'numeric', 'min:0'],
            'descripcion_sancion' => ['nullable', 'string', 'min:5', 'max:1000'],
            'motivo_resolucion' => ['required', 'string', 'min:10', 'max:2000'],
            'validar_inmediatamente' => ['nullable', 'boolean'],
            'publicar_en_boletin' => ['nullable', 'boolean'],
            'cerrar_expediente' => ['nullable', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (! $this->filled('persona_id') && ! $this->filled('equipo_id')) {
                $v->errors()->add('persona_id', 'Debe seleccionar al menos una persona o un equipo sancionado.');
            }

            $tipo = (string) $this->input('tipo_sancion');
            if (in_array($tipo, ['partidos_suspension', 'suspension_equipo'], true) && (int) $this->input('partidos_suspendidos', 0) <= 0) {
                $v->errors()->add('partidos_suspendidos', 'Para este tipo de sanción debe indicar al menos 1 partido de suspensión.');
            }

            if ($tipo === 'multa_economica' && (float) $this->input('monto_multa', 0) <= 0) {
                $v->errors()->add('monto_multa', 'Una multa económica requiere especificar un monto mayor a cero.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_sancion.required' => 'El tipo de sanción es obligatorio.',
            'tipo_sancion.in' => 'El tipo de sanción seleccionado no es válido.',
            'descripcion_sancion.min' => 'La descripción de la sanción debe tener al menos 5 caracteres.',
            'motivo_resolucion.required' => 'La fundamentación jurídico-deportiva de la resolución es obligatoria.',
            'motivo_resolucion.min' => 'El motivo o fundamentación de la resolución debe tener al menos 10 caracteres.',
            'fecha_fin.after_or_equal' => 'La fecha de término debe ser igual o posterior a la fecha de inicio.',
            'persona_id.exists' => 'La persona seleccionada no existe en el catálogo.',
            'equipo_id.exists' => 'El equipo seleccionado no existe en el catálogo.',
            'articulo_id.exists' => 'El artículo reglamentario seleccionado no existe en el catálogo.',
        ];
    }
}
