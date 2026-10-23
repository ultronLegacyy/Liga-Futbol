<?php

namespace App\Http\Requests;

use App\Models\Cancha;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBloqueoCanchaRequest extends FormRequest
{
    /**
     * Determina si el usuario esta autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('campos.bloquear')
            || $this->user()?->hasRole('Administrador General'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date', 'before:fecha_fin'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.required' => 'La fecha y hora de inicio del bloqueo es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha y hora valida.',
            'fecha_inicio.before' => 'La fecha de inicio debe ser anterior a la fecha de termino.',
            'fecha_fin.required' => 'La fecha y hora de termino del bloqueo es obligatoria.',
            'fecha_fin.date' => 'La fecha de termino debe ser una fecha y hora valida.',
            'fecha_fin.after' => 'La fecha de termino debe ser posterior a la fecha de inicio.',
            'motivo.required' => 'El motivo del bloqueo es obligatorio.',
            'motivo.min' => 'El motivo del bloqueo debe contener al menos :min caracteres.',
            'motivo.max' => 'El motivo del bloqueo no puede exceder :max caracteres.',
        ];
    }

    /**
     * Validacion de regla de negocio: prevencion inquebrantable de traslapes de horarios en la misma cancha (HU-024, CA3).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $parametroCancha = $this->route('cancha');
            $cancha = $parametroCancha instanceof Cancha
                ? $parametroCancha
                : Cancha::find($parametroCancha);

            if (! $cancha) {
                return;
            }

            $inicio = Carbon::parse((string) $this->input('fecha_inicio'));
            $fin = Carbon::parse((string) $this->input('fecha_fin'));

            $existeSolape = $cancha->bloqueos()
                ->where('fecha_inicio', '<', $fin)
                ->where('fecha_fin', '>', $inicio)
                ->exists();

            if ($existeSolape) {
                $validator->errors()->add(
                    'fecha_inicio',
                    'La cancha ya cuenta con un bloqueo registrado que se traslapa con el horario seleccionado.'
                );
            }
        });
    }
}
