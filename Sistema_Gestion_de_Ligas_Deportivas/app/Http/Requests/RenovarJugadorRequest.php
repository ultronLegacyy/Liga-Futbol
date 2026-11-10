<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RenovarJugadorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $jugador = $this->route('jugador');

        return $jugador instanceof \App\Models\Jugador && Gate::allows('renew', $jugador);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'equipo_id' => ['required', 'integer', 'exists:equipos,id'],
            'dorsal' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
