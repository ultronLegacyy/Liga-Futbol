<?php

namespace App\Http\Requests;

use App\Models\Grupo;
use App\Models\LlaveLiguilla;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// HU-056: crear la siguiente ronda de la liguilla con las fechas de su jornada (Presidente y Administrador General)
class CrearRondaLiguillaRequest extends FormRequest
{
    public function authorize(): Response
    {
        $grupo = $this->route('grupo');

        return $grupo instanceof Grupo
            ? Gate::forUser($this->user())->inspect('crearRonda', [LlaveLiguilla::class, $grupo])
            : Response::deny('El grupo no existe.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
            'fecha_fin' => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.required' => 'Indica la fecha de inicio de la ronda.',
            'fecha_inicio.string' => 'La fecha de inicio no es válida.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'fecha_fin.required' => 'Indica la fecha de fin de la ronda.',
            'fecha_fin.string' => 'La fecha de fin no es válida.',
            'fecha_fin.date_format' => 'La fecha de fin debe tener el formato AAAA-MM-DD.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
