<?php

namespace App\Http\Requests\Cedula;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// HU-036: solo el árbitro asignado abre la cédula, con una acción explícita; no lleva datos
class AbrirCedulaRequest extends FormRequest
{
    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $partido = $this->route('partido');

        return $partido instanceof Partido
            ? Gate::forUser($this->user())->inspect('abrir', [CedulaArbitral::class, $partido])
            : Response::deny('El partido no existe.');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
