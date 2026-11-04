<?php

namespace App\Http\Requests;

use App\Models\Equipo;
use App\Models\Jugador;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJugadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipo = $this->route('equipo');

        return $equipo instanceof Equipo
            && ($this->user()?->can('addPlayer', $equipo) ?? false);
    }

    public function rules(): array
    {
        $equipo = $this->route('equipo');

        if (! $equipo instanceof Equipo) {
            return [];
        }

        return [
            'persona_id' => [
                'required',
                'integer',
                Rule::exists('personas', 'id'),
                Rule::unique('jugadores_equipos', 'persona_id')->where('equipo_id', $equipo->id),
            ],
            'dorsal' => [
                'nullable',
                'integer',
                'min:1',
                'max:99',
                Rule::unique('jugadores_equipos', 'dorsal')->where('equipo_id', $equipo->id),
            ],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $equipo = $this->route('equipo');

            if (! $equipo instanceof Equipo
                || $validator->errors()->has('persona_id')
                || ! $this->filled('persona_id')
                || $equipo->temporada_id === null) {
                return;
            }

            $personaActivaEnOtroEquipo = Jugador::query()
                ->where('persona_id', $this->integer('persona_id'))
                ->where('estado_elegibilidad', 'Activo')
                ->where('equipo_id', '!=', $equipo->getKey())
                ->whereHas('equipo', fn (Builder $consulta): Builder => $consulta->where('temporada_id', $equipo->temporada_id))
                ->exists();

            if ($personaActivaEnOtroEquipo) {
                $validator->errors()->add('persona_id', 'El jugador ya está activo en otro equipo de esta temporada.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'persona_id.required' => 'El jugador es obligatorio.',
            'persona_id.exists' => 'El jugador seleccionado no existe.',
            'persona_id.unique' => 'El jugador ya está registrado en este equipo.',
            'dorsal.integer' => 'El dorsal debe ser un número entero.',
            'dorsal.min' => 'El dorsal debe ser al menos :min.',
            'dorsal.max' => 'El dorsal no debe ser mayor que :max.',
            'dorsal.unique' => 'El dorsal ya está asignado a otro jugador de este equipo.',
        ];
    }
}
