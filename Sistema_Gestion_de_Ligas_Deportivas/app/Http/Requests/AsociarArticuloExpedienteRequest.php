<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\ExpedienteDisciplinario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validación y autorización para la imputación de artículos reglamentarios presuntos en un expediente (HU-044 / RF-10.1).
 */
class AsociarArticuloExpedienteRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto {
        prepareForValidation as descartarArreglos;
    }

    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        if (! $expediente instanceof ExpedienteDisciplinario) {
            $expediente = ExpedienteDisciplinario::findOrFail($expediente);
        }

        $response = Gate::inspect('gestionar', $expediente);

        if ($response->denied()) {
            $response->authorize();
        }

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'articulo_id' => ['bail', 'required', 'integer', 'exists:catalogo_articulos,id'],
            'observaciones' => ['bail', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'articulo_id.required' => 'Debes seleccionar un artículo del catálogo reglamentario.',
            'articulo_id.integer' => 'El artículo seleccionado no es válido.',
            'articulo_id.exists' => 'El artículo reglamentario seleccionado no existe en el catálogo.',
            'observaciones.string' => 'Las observaciones deben ser una cadena de texto.',
            'observaciones.max' => 'Las observaciones no deben exceder de :max caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->descartarArreglos();
    }

    /**
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return ['observaciones'];
    }
}
