<?php

namespace App\Http\Requests\Cedula;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// HU-075: cierre de la cédula después de revisar el resumen (criterio 4)
class CerrarCedulaRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $partido = $this->route('partido');

        return $partido instanceof Partido
            ? Gate::forUser($this->user())->inspect('cerrar', [CedulaArbitral::class, $partido])
            : Response::deny('El partido no existe.');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['confirmar' => ['bail', 'accepted']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['confirmar.accepted' => 'Confirma que revisaste el resumen antes de cerrar la cédula.'];
    }
}
