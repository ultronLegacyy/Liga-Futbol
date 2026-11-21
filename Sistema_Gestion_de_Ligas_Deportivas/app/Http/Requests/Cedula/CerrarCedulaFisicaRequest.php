<?php

namespace App\Http\Requests\Cedula;

use App\Models\CedulaArbitral;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * HU-037, respuesta 50 del PO: el Capturista cierra la cédula transcrita con el escaneo de la cédula en papel
 * firmada por el árbitro y los capitanes, después de revisar lo capturado (RNF-16).
 */
class CerrarCedulaFisicaRequest extends FormRequest
{
    // Los errores del cierre se muestran en su propia sección del formulario
    protected $errorBag = 'cierre';

    /** Devuelve la respuesta de la política para propagar su mensaje en español. */
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('capturarFisica', CedulaArbitral::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        // Fotografía o PDF de la cédula, de hasta 5 MB (RNF-09)
        return [
            'cedula_escaneada' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'confirmar' => ['bail', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cedula_escaneada.required' => 'Adjunta la foto o el escaneo de la cédula en papel firmada por el árbitro y los capitanes.',
            'cedula_escaneada.file' => 'Adjunta la foto o el escaneo de la cédula en papel firmada por el árbitro y los capitanes.',
            'cedula_escaneada.mimes' => 'El escaneo de la cédula debe ser una imagen JPG o PNG, o un PDF.',
            'cedula_escaneada.max' => 'El escaneo de la cédula debe pesar como máximo 5 MB.',
            'confirmar.accepted' => 'Confirma que revisaste lo capturado contra la cédula en papel antes de cerrarla.',
        ];
    }

    public function escaneo(): UploadedFile
    {
        /** @var UploadedFile $archivo */
        $archivo = $this->file('cedula_escaneada');

        return $archivo;
    }
}
