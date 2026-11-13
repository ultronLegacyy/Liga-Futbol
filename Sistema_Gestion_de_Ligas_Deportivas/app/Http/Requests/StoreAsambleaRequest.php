<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Asamblea;
use App\Services\GestionJugadorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// Convocatoria a asamblea de delegados (HU-053, RF-14.1)
class StoreAsambleaRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public function authorize(): bool
    {
        // Gate::authorize conserva el mensaje en español de la política en el 403.
        Gate::authorize('create', Asamblea::class);

        return true;
    }

    /** «Hoy» con la hora de México: desde las 18:00 la zona de la app (UTC) ya va en el día siguiente. */
    public static function hoy(): string
    {
        return now(GestionJugadorService::ZONA_HORARIA)->toDateString();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tipo' => ['bail', 'required', 'string', Rule::in(Asamblea::TIPOS)],
            'fecha' => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:'.self::hoy()],
            'hora' => ['bail', 'required', 'string', 'date_format:H:i'],
            'lugar' => ['bail', 'required', 'string', 'min:3', 'max:200'],
            'orden_del_dia' => ['bail', 'required', 'string', 'min:5', 'max:5000'],
            'temporada_id' => ['bail', 'nullable', 'integer', Rule::exists('temporadas', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tipo.required' => 'Selecciona el tipo de asamblea.',
            'tipo.string' => 'El tipo de asamblea debe ser Ordinaria o Extraordinaria.',
            'tipo.in' => 'El tipo de asamblea debe ser Ordinaria o Extraordinaria.',
            'fecha.required' => 'La fecha de la asamblea es obligatoria.',
            'fecha.string' => 'La fecha de la asamblea no es válida.',
            'fecha.date_format' => 'La fecha de la asamblea no es válida; usa el calendario (AAAA-MM-DD).',
            'fecha.after_or_equal' => 'No se puede convocar una asamblea en una fecha pasada.',
            'hora.required' => 'La hora de la asamblea es obligatoria.',
            'hora.string' => 'La hora de la asamblea no es válida.',
            'hora.date_format' => 'La hora debe tener el formato HH:MM.',
            'lugar.required' => 'El lugar de la asamblea es obligatorio.',
            'lugar.string' => 'El lugar de la asamblea debe ser texto.',
            'lugar.min' => 'El lugar debe tener al menos :min caracteres.',
            'lugar.max' => 'El lugar no debe tener más de :max caracteres.',
            'orden_del_dia.required' => 'El orden del día es obligatorio.',
            'orden_del_dia.string' => 'El orden del día debe ser texto.',
            'orden_del_dia.min' => 'Describe el orden del día de la asamblea.',
            'orden_del_dia.max' => 'El orden del día no debe tener más de :max caracteres.',
            'temporada_id.integer' => 'Selecciona una temporada de la lista.',
            'temporada_id.exists' => 'La temporada seleccionada no existe.',
        ];
    }

    /** @return list<string> */
    protected function camposDeTexto(): array
    {
        return ['tipo', 'fecha', 'hora', 'lugar', 'orden_del_dia', 'temporada_id'];
    }
}
