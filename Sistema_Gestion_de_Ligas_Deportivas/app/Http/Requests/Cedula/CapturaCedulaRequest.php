<?php

namespace App\Http\Requests\Cedula;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Base de los cambios capturados en la cédula (HU-036): la política decide quién captura y cada cambio
 * lleva la clave de envío (UUID) que genera el navegador, para que un reenvío no duplique nada (RNF-21).
 */
abstract class CapturaCedulaRequest extends FormRequest
{
    // Habilidad de la política que autoriza la operación
    protected string $habilidad = 'capturar';

    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $partido = $this->route('partido');

        return $partido instanceof Partido
            ? Gate::forUser($this->user())->inspect($this->habilidad, [CedulaArbitral::class, $partido])
            : Response::deny('El partido no existe.');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['clave_envio' => ['bail', 'required', 'string', 'uuid'], ...$this->reglas()];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'clave_envio.required' => 'Falta la clave de envío del cambio.',
            'clave_envio.string' => 'La clave de envío no es válida.',
            'clave_envio.uuid' => 'La clave de envío no es válida.',
            ...$this->mensajes(),
        ];
    }

    public function clave(): string
    {
        return (string) $this->validated('clave_envio');
    }

    /** @return array<string, list<mixed>> */
    abstract protected function reglas(): array;

    /** @return array<string, string> */
    abstract protected function mensajes(): array;
}
