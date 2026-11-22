<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Asamblea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// Lista de asistencia de los delegados a una asamblea (HU-053, RF-14.1)
class GuardarAsistenciaAsambleaRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public function authorize(): bool
    {
        $asamblea = $this->route('asamblea');
        // Gate::authorize conserva el mensaje en español de la política en el 403.
        Gate::authorize('registrarAsistencia', $asamblea instanceof Asamblea ? $asamblea : Asamblea::class);

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'asistencias' => ['bail', 'required', 'array'],
            'asistencias.*' => ['bail', 'array'],
            'asistencias.*.asistio' => ['bail', 'nullable', 'boolean'],
            'asistencias.*.delegado_usuario_id' => ['bail', 'nullable', 'integer'],
            'asistencias.*.nombre_asistente' => ['bail', 'nullable', 'string', 'max:200'],
            'asistencias.*.observaciones' => ['bail', 'nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'asistencias.required' => 'La lista de asistencia está vacía.',
            'asistencias.array' => 'La lista de asistencia no es válida.',
            'asistencias.*.array' => 'La asistencia de cada club no es válida.',
            'asistencias.*.asistio.boolean' => 'Indica con la casilla si el club asistió.',
            'asistencias.*.delegado_usuario_id.integer' => 'Selecciona un delegado de la lista.',
            'asistencias.*.nombre_asistente.string' => 'El nombre del asistente debe ser texto.',
            'asistencias.*.nombre_asistente.max' => 'El nombre del asistente no debe superar los :max caracteres.',
            'asistencias.*.observaciones.string' => 'Las observaciones deben ser texto.',
            'asistencias.*.observaciones.max' => 'Las observaciones no deben superar los :max caracteres.',
        ];
    }

    /** @return list<string> */
    protected function camposDeTexto(): array
    {
        return [
            'asistencias.*.asistio',
            'asistencias.*.delegado_usuario_id',
            'asistencias.*.nombre_asistente',
            'asistencias.*.observaciones',
        ];
    }
}
