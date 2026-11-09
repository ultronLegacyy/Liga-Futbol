<?php

namespace App\Http\Requests;

use App\Models\Jornada;
use Illuminate\Foundation\Http\FormRequest;

// Confirmación y persistencia del rol regular generado (HU-032, RF-07.1)
class ConfirmarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grupo = $this->route('grupo');

        return $this->user()?->can('create', [Jornada::class, $grupo]) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'fixture_payload' => ['required', 'string', 'json'],
            'sobrescribir' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fixture_payload.required' => 'La propuesta de enfrentamientos es requerida para su confirmación.',
            'fixture_payload.string' => 'El formato de la propuesta de enfrentamientos no es válido.',
            'fixture_payload.json' => 'La propuesta de enfrentamientos debe ser una estructura JSON válida.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $grupo = $this->route('grupo');
            if (! $grupo instanceof \App\Models\Grupo) {
                return;
            }

            /** @var string $payloadString */
            $payloadString = (string) $this->input('fixture_payload');
            $propuesta = json_decode($payloadString, true);

            if (! is_array($propuesta)) {
                $validator->errors()->add('fixture_payload', 'La propuesta de enfrentamientos debe ser una estructura JSON válida.');

                return;
            }

            try {
                app(\App\Services\GeneradorEnfrentamientosService::class)->validarPropuestaRol($grupo, $propuesta);
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->validator->errors()->all() as $mensaje) {
                    $validator->errors()->add('fixture_payload', $mensaje);
                }
            }
        });
    }
}
