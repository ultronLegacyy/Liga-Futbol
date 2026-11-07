<?php

namespace App\Http\Requests;

use App\Models\Jugador;
use Illuminate\Foundation\Http\FormRequest;

class BajaJugadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jugador = $this->route('jugador');

        return $jugador instanceof Jugador
            && ($this->user()?->can('deactivate', $jugador) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
            'fecha_baja' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de baja es obligatorio.',
            'motivo.min' => 'El motivo de baja debe contener al menos :min caracteres.',
            'motivo.max' => 'El motivo de baja no puede exceder :max caracteres.',
            'fecha_baja.required' => 'La fecha de baja es obligatoria.',
            'fecha_baja.date' => 'La fecha de baja no es válida.',
            'fecha_baja.before_or_equal' => 'La fecha de baja no puede ser futura.',
        ];
    }
}
