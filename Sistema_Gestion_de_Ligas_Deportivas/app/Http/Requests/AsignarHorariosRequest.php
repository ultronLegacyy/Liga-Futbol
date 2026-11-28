<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Http\Requests\Concerns\ValidaOpcionesHorarios;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Temporada;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Solicitud de ejecución del motor automático de asignación de horarios y canchas (HU-074 / RF-07.2).
 */
class AsignarHorariosRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;
    use ValidaOpcionesHorarios;

    /**
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return ['horarios_texto', 'horarios.*'];
    }

    public function authorize(): Response
    {
        $jornada = $this->route('jornada');
        if ($jornada instanceof Jornada) {
            return Gate::inspect('update', $jornada);
        }

        $grupo = $this->route('grupo');
        if ($grupo instanceof Grupo) {
            return Gate::inspect('create', [Jornada::class, $grupo]);
        }

        $temporada = $this->route('temporada');
        if ($temporada instanceof Temporada) {
            return Gate::inspect('programarMasivo', $temporada);
        }

        return Response::deny('No se identificó el destino de la programación automática.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->reglasOpcionesHorarios(), [
            'reemplazar_existentes' => ['bail', 'nullable', 'boolean'],
            'en_segundo_plano' => ['bail', 'nullable', 'boolean'],
            'numero_jornada' => ['bail', 'nullable', 'integer', 'min:1', 'max:200'],
        ]);
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
        return array_merge($this->mensajesOpcionesHorarios(), [
            'reemplazar_existentes.boolean' => 'La opción de reasignación total debe ser verdadera o falsa.',
            'en_segundo_plano.boolean' => 'La opción de ejecución en segundo plano debe ser verdadera o falsa.',
            'numero_jornada.integer' => 'El número de jornada debe ser un número entero positivo.',
            'numero_jornada.min' => 'El número de jornada debe ser al menos 1.',
            'numero_jornada.max' => 'El número de jornada no puede ser mayor que 200.',
        ]);
    }
}
