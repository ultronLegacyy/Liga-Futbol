<?php

namespace App\Http\Requests\Cedula;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// HU-075: firma del capitán dibujada con el dedo; llega como PNG en base64 (data URL)
class FirmarCedulaRequest extends FormRequest
{
    private const PREFIJO = 'data:image/png;base64,';

    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        $partido = $this->route('partido');

        return $partido instanceof Partido
            ? Gate::forUser($this->user())->inspect('firmar', [CedulaArbitral::class, $partido])
            : Response::deny('El partido no existe.');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        // 300 KB de PNG ocupan unos 410 000 caracteres en base64
        return ['firma' => ['bail', 'required', 'string', 'starts_with:'.self::PREFIJO, 'max:410000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'firma.required' => 'Falta la firma del capitán.',
            'firma.string' => 'La firma no es válida.',
            'firma.starts_with' => 'La firma debe ser una imagen PNG.',
            'firma.max' => 'La firma no es una imagen PNG válida de hasta 300 KB.',
        ];
    }

    // El PNG ya decodificado; si el base64 no es válido queda vacío y el servicio lo rechaza
    public function png(): string
    {
        $decodificado = base64_decode(substr((string) $this->validated('firma'), strlen(self::PREFIJO)), true);

        return $decodificado === false ? '' : $decodificado;
    }
}
