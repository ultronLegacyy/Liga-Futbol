<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Http\Requests\Concerns\ValidaOpcionesHorarios;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Services\GeneradorEnfrentamientosService;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

// Confirmación y persistencia del rol regular generado (HU-032, RF-07.1, HU-074)
class ConfirmarRolRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;
    use ValidaOpcionesHorarios;

    /**
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return ['fixture_payload', 'horarios_texto', 'horarios.*'];
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
        return array_merge([
            'fixture_payload' => ['required', 'string', 'json'],
            'sobrescribir' => ['nullable', 'boolean'],
            'asignar_horarios' => ['nullable', 'boolean'],
        ], $this->reglasOpcionesHorarios());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'fixture_payload.required' => 'La propuesta de enfrentamientos es requerida para su confirmación.',
            'fixture_payload.string' => 'El formato de la propuesta de enfrentamientos no es válido.',
            'fixture_payload.json' => 'La propuesta de enfrentamientos debe ser una estructura JSON válida.',
        ], $this->mensajesOpcionesHorarios());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validarHorariosTexto($validator);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $grupo = $this->route('grupo');
            if (! $grupo instanceof Grupo) {
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
                app(GeneradorEnfrentamientosService::class)->validarPropuestaRol($grupo, $propuesta);
            } catch (ValidationException $e) {
                foreach ($e->validator->errors()->all() as $mensaje) {
                    $validator->errors()->add('fixture_payload', $mensaje);
                }
            }
        });
    }
}
