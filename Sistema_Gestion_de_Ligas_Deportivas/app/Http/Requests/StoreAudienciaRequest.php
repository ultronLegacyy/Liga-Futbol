<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\AudienciaDisciplinaria;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreAudienciaRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto {
        prepareForValidation as protected descartarArreglos;
    }

    public function authorize(): Response
    {
        $usuario = $this->user();
        if (! $usuario instanceof User) {
            return Response::deny('Debe iniciar sesión para registrar audiencias disciplinarias.');
        }

        return Gate::forUser($usuario)->inspect('create', AudienciaDisciplinaria::class);
    }

    /**
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return [
            'fecha_audiencia',
            'lugar_o_medio',
            'comparecientes',
            'alegatos_defensa',
            'resolucion',
            'motivo',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->descartarArreglos();

        $fecha = $this->input('fecha_audiencia');
        if (is_string($fecha)) {
            $fechaLimpia = trim($fecha);
            // Acepta formato HTML5 datetime-local (YYYY-MM-DDTHH:MM) y normaliza únicamente ISO estricto.
            if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $fechaLimpia) === 1) {
                $this->merge(['fecha_audiencia' => str_replace('T', ' ', $fechaLimpia).':00']);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $fechaLimpia) === 1) {
                $this->merge(['fecha_audiencia' => str_replace('T', ' ', $fechaLimpia)]);
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fecha_audiencia' => ['bail', 'required', 'string', 'date_format:Y-m-d H:i:s'],
            'lugar_o_medio' => ['bail', 'required', 'string', 'min:3', 'max:150'],
            'comparecientes' => ['bail', 'required', 'string', 'min:5', 'max:1000'],
            'alegatos_defensa' => ['bail', 'required', 'string', 'min:10', 'max:3000'],
            'resolucion' => ['bail', 'nullable', 'string', 'max:3000'],
            'motivo' => ['bail', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_audiencia.required' => 'La fecha de la audiencia es obligatoria.',
            'fecha_audiencia.string' => 'La fecha de la audiencia debe ser una cadena de texto válida.',
            'fecha_audiencia.date_format' => 'La fecha de la audiencia debe tener un formato válido (YYYY-MM-DD HH:MM).',
            'lugar_o_medio.required' => 'El lugar o medio de la audiencia es obligatorio.',
            'lugar_o_medio.string' => 'El lugar o medio de la audiencia debe ser una cadena de texto válida.',
            'lugar_o_medio.min' => 'El lugar o medio de la audiencia debe contener al menos 3 caracteres.',
            'lugar_o_medio.max' => 'El lugar o medio de la audiencia no debe superar los 150 caracteres.',
            'comparecientes.required' => 'Debe registrar a los comparecientes presentes en la audiencia.',
            'comparecientes.string' => 'El listado de comparecientes debe ser texto válido.',
            'comparecientes.min' => 'El listado de comparecientes debe contener al menos 5 caracteres.',
            'comparecientes.max' => 'El listado de comparecientes no debe superar los 1000 caracteres.',
            'alegatos_defensa.required' => 'Los alegatos de defensa o descargos son obligatorios.',
            'alegatos_defensa.string' => 'Los alegatos de defensa deben ser texto válido.',
            'alegatos_defensa.min' => 'Los alegatos de defensa deben contener al menos 10 caracteres.',
            'alegatos_defensa.max' => 'Los alegatos de defensa no deben superar los 3000 caracteres.',
            'resolucion.string' => 'La resolución preliminar debe ser texto válido.',
            'resolucion.max' => 'La resolución preliminar no debe superar los 3000 caracteres.',
            'motivo.string' => 'El motivo de auditoría debe ser texto válido.',
            'motivo.max' => 'El motivo de auditoría no debe superar los 500 caracteres.',
        ];
    }
}
