<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DespublicarBoletinRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public function authorize(): Response
    {
        $usuario = $this->user();
        if (! $usuario instanceof User) {
            return Response::deny('Debe iniciar sesión para retirar sanciones del boletín oficial.');
        }

        return Gate::forUser($usuario)->inspect('publicar', Sancion::class);
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
            'motivo.required' => 'Debe registrar el motivo del retiro de la sanción del boletín oficial.',
            'motivo.string' => 'El motivo de retiro debe ser texto válido.',
            'motivo.min' => 'El motivo del retiro debe contener al menos 5 caracteres.',
            'motivo.max' => 'El motivo del retiro no debe superar los 500 caracteres.',
        ];
    }
}
