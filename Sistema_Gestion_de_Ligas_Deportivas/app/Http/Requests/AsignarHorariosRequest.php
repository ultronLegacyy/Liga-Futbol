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
            if ($temporada->estado === 'cerrada') {
                return Response::deny('Una temporada cerrada no admite cambios en su programación.');
            }

            $usuario = $this->user();
            if ($usuario === null) {
                return Response::deny('Debe iniciar sesión para programar horarios.');
            }

            return ($usuario->hasRole('Administrador General') || $usuario->can('partidos.programar'))
                ? Response::allow()
                : Response::deny('No cuenta con privilegios para ejecutar la programación automática masiva.');
        }

        return Response::deny('No se identificó el destino de la programación automática.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->reglasOpcionesHorarios(), [
            'reemplazar_existentes' => ['nullable', 'boolean'],
            'en_segundo_plano' => ['nullable', 'boolean'],
            'numero_jornada' => ['nullable', 'integer', 'min:1', 'max:200'],
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
            'numero_jornada.integer' => 'El número de jornada debe ser un entero positivo.',
        ]);
    }
}
