<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Asamblea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// Comunicado de asamblea (HU-054, RF-14.2)
class StoreComunicadoAsambleaRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public function authorize(): bool
    {
        $asamblea = $this->route('asamblea');
        // Gate::authorize conserva el mensaje en español de la política en el 403.
        Gate::authorize('registrarAcuerdos', $asamblea instanceof Asamblea ? $asamblea : Asamblea::class);

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'titulo' => ['bail', 'required', 'string', 'min:3', 'max:200'],
            'contenido' => ['bail', 'required', 'string', 'min:5', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'titulo.required' => 'El título del comunicado es obligatorio.',
            'titulo.string' => 'El título del comunicado debe ser texto.',
            'titulo.min' => 'El título del comunicado debe tener al menos :min caracteres.',
            'titulo.max' => 'El título del comunicado no debe tener más de :max caracteres.',
            'contenido.required' => 'El contenido del comunicado es obligatorio.',
            'contenido.string' => 'El contenido del comunicado debe ser texto.',
            'contenido.min' => 'El contenido del comunicado debe tener al menos :min caracteres.',
            'contenido.max' => 'El contenido del comunicado no debe tener más de :max caracteres.',
        ];
    }

    /** @return list<string> */
    protected function camposDeTexto(): array
    {
        return ['titulo', 'contenido'];
    }
}
