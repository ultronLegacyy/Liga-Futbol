<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Grupo;
use App\Models\Jornada;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validación de preferencias horarias por equipo registradas por el Programador de Partidos (PO / HU-074).
 */
class StorePreferenciasGrupoRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    /**
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return ['preferencias.*.dia_preferido', 'preferencias.*.hora_preferida'];
    }

    public function authorize(): Response
    {
        $grupo = $this->route('grupo');

        return Gate::inspect('create', [Jornada::class, $grupo instanceof Grupo ? $grupo : null]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'preferencias' => ['bail', 'required', 'array'],
            'preferencias.*' => ['bail', 'array'],
            'preferencias.*.cancha_id_preferida' => ['bail', 'nullable', 'integer', 'exists:canchas,id'],
            'preferencias.*.dia_preferido' => ['bail', 'nullable', 'string', 'in:Lunes,Martes,Miércoles,Jueves,Viernes,Sábado,Domingo'],
            'preferencias.*.hora_preferida' => ['bail', 'nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preferencias.required' => 'Debe enviar la lista de preferencias de equipos del grupo.',
            'preferencias.array' => 'Las preferencias deben enviarse en formato de lista válido.',
            'preferencias.*.array' => 'El bloque de preferencias de cada equipo no tiene un formato válido.',
            'preferencias.*.cancha_id_preferida.integer' => 'La cancha preferida debe ser un identificador entero válido.',
            'preferencias.*.cancha_id_preferida.exists' => 'La cancha preferida seleccionada no existe en el catálogo.',
            'preferencias.*.dia_preferido.string' => 'El día preferido debe enviarse como texto válido.',
            'preferencias.*.dia_preferido.in' => 'El día preferido debe ser un día válido de la semana (Lunes a Domingo).',
            'preferencias.*.hora_preferida.string' => 'La hora preferida debe enviarse en formato HH:MM.',
            'preferencias.*.hora_preferida.regex' => 'La hora preferida debe tener formato válido HH:MM entre 00:00 y 23:59.',
        ];
    }
}
