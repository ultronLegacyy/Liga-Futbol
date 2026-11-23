<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ValidarSancionRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public function authorize(): Response
    {
        $usuario = $this->user();
        if (! $usuario instanceof User) {
            return Response::deny('Debe iniciar sesión para validar sanciones disciplinarias.');
        }

        $sancion = $this->route('sancion');
        if (! $sancion instanceof Sancion) {
            return Response::deny('Sanción disciplinaria no localizada.');
        }

        return Gate::forUser($usuario)->inspect('validar', $sancion);
    }

    /**
     * @return list<string>
     */
    protected function camposDeTexto(): array
    {
        return ['motivo'];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['bail', 'required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe registrar el motivo de la validación colegiada de la sanción.',
            'motivo.string' => 'El motivo de validación debe ser texto válido.',
            'motivo.min' => 'El motivo de la validación debe contener al menos 5 caracteres.',
            'motivo.max' => 'El motivo de la validación no debe superar los 500 caracteres.',
        ];
    }
}
