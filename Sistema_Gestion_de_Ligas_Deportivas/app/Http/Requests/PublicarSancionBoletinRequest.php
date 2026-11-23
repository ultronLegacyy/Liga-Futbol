<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PublicarSancionBoletinRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public function authorize(): Response
    {
        $usuario = $this->user();
        if (! $usuario instanceof User) {
            return Response::deny('Debe iniciar sesión para publicar en el boletín oficial.');
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
            'motivo' => ['bail', 'nullable', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.string' => 'El motivo de publicación debe ser texto válido.',
            'motivo.min' => 'El motivo de publicación debe contener al menos 5 caracteres.',
            'motivo.max' => 'El motivo de publicación no debe superar los 500 caracteres.',
        ];
    }
}
