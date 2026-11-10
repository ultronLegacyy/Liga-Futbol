<?php

namespace App\Http\Requests;

use App\Models\Jugador;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** HU-017, criterio 3: transferencia entre clubes con las reglas de la temporada vigente. */
class TransferirJugadorRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $jugador = $this->route('jugador');

        return $jugador instanceof Jugador
            ? Gate::forUser($this->user())->inspect('transfer', $jugador)
            : Response::deny('El jugador no existe.');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'equipo_id' => ['bail', 'required', 'integer', Rule::exists('equipos', 'id')],
            'dorsal' => ['bail', 'nullable', 'integer', 'min:1', 'max:99'],
            // El servidor decide el origen, el ejecutor, el estado y el historial.
            'club_origen_id' => ['prohibited'],
            'transferido_por_usuario_id' => ['prohibited'],
            'estado_elegibilidad' => ['prohibited'],
            'historial_transferencia_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'equipo_id.required' => 'Selecciona el equipo del club de destino.',
            'equipo_id.integer' => 'El equipo seleccionado no es válido.',
            'equipo_id.exists' => 'El equipo seleccionado no existe.',
            'dorsal.integer' => 'El dorsal debe ser un número entero.',
            'dorsal.min' => 'El dorsal debe ser al menos :min.',
            'dorsal.max' => 'El dorsal no puede ser mayor que :max.',
            'club_origen_id.prohibited' => 'El club de origen no se puede enviar; lo determina el sistema.',
            'transferido_por_usuario_id.prohibited' => 'El usuario que transfiere no se puede enviar; lo determina el sistema.',
            'estado_elegibilidad.prohibited' => 'El estado no se puede enviar en una transferencia.',
            'historial_transferencia_id.prohibited' => 'El historial de la transferencia no se puede enviar.',
        ];
    }
}
