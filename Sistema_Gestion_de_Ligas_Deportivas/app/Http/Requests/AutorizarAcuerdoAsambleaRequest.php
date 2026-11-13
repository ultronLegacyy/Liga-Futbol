<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\AcuerdoAsamblea;
use App\Models\Asamblea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Convertir un acuerdo en regla permanente (HU-054, RF-14.3).
 *
 * Los errores van a una bolsa por acuerdo (acuerdo_{id}), para que el mensaje aparezca
 * solo debajo del acuerdo que se intentó autorizar.
 */
class AutorizarAcuerdoAsambleaRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto {
        prepareForValidation as descartarArreglos;
    }

    public function authorize(): bool
    {
        $asamblea = $this->route('asamblea');
        // Gate::authorize conserva el mensaje en español de la política en el 403.
        Gate::authorize('autorizarReglaPermanente', $asamblea instanceof Asamblea ? $asamblea : Asamblea::class);

        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'motivo' => ['bail', 'required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Explica por qué el acuerdo se vuelve regla permanente.',
            'motivo.string' => 'El motivo debe ser texto.',
            'motivo.min' => 'Explica por qué el acuerdo se vuelve regla permanente (al menos :min caracteres).',
            'motivo.max' => 'El motivo no debe tener más de :max caracteres.',
        ];
    }

    public static function bolsaDeErrores(AcuerdoAsamblea $acuerdo): string
    {
        return 'acuerdo_'.$acuerdo->getKey();
    }

    protected function prepareForValidation(): void
    {
        $acuerdo = $this->route('acuerdo');

        if ($acuerdo instanceof AcuerdoAsamblea) {
            $this->errorBag = self::bolsaDeErrores($acuerdo);
        }

        $this->descartarArreglos();
    }

    /** @return list<string> */
    protected function camposDeTexto(): array
    {
        return ['motivo'];
    }
}
