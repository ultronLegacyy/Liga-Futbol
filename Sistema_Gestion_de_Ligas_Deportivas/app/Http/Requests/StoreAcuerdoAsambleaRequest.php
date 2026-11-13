<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Asamblea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// Acuerdo de asamblea e indicación de si modifica el reglamento (HU-054, RF-14.2)
class StoreAcuerdoAsambleaRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public function authorize(): bool
    {
        $asamblea = $this->route('asamblea');
        // Gate::authorize conserva el mensaje en español de la política en el 403.
        Gate::authorize('registrarAcuerdos', $asamblea instanceof Asamblea ? $asamblea : Asamblea::class);

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'descripcion' => ['bail', 'required', 'string', 'min:5', 'max:5000'],
            'modifica_reglamento' => ['bail', 'required', 'boolean'],
            'temporada_id' => ['bail', 'nullable', 'integer', Rule::exists('temporadas', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'Describe el acuerdo.',
            'descripcion.string' => 'El acuerdo debe ser texto.',
            'descripcion.min' => 'Describe el acuerdo.',
            'descripcion.max' => 'El acuerdo no debe tener más de :max caracteres.',
            'modifica_reglamento.required' => 'Indica si el acuerdo modifica el reglamento de la temporada.',
            'modifica_reglamento.boolean' => 'Indica con Sí o No si el acuerdo modifica el reglamento de la temporada.',
            'temporada_id.integer' => 'Selecciona una temporada de la lista.',
            'temporada_id.exists' => 'La temporada seleccionada no existe.',
        ];
    }

    /** @return list<string> */
    protected function camposDeTexto(): array
    {
        return ['descripcion', 'modifica_reglamento', 'temporada_id'];
    }
}
