<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PublicarBoletinRequest extends FormRequest
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
        return ['motivo', 'sanciones_ids.*'];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sanciones_ids' => ['bail', 'required', 'array', 'min:1'],
            'sanciones_ids.*' => ['bail', 'required', 'integer', 'exists:sanciones,id'],
            'motivo' => ['bail', 'required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sanciones_ids.required' => 'Debe seleccionar al menos una sanción para publicar en el boletín.',
            'sanciones_ids.array' => 'El listado de sanciones seleccionadas no tiene un formato válido.',
            'sanciones_ids.min' => 'Debe seleccionar al menos una sanción.',
            'sanciones_ids.*.required' => 'Cada sanción seleccionada debe contener un identificador válido.',
            'sanciones_ids.*.integer' => 'Cada identificador de sanción debe ser un número entero válido.',
            'sanciones_ids.*.exists' => 'Alguna de las sanciones seleccionadas no existe en el registro.',
            'motivo.required' => 'Debe registrar el motivo de la publicación oficial del boletín.',
            'motivo.string' => 'El motivo de publicación debe ser texto válido.',
            'motivo.min' => 'El motivo de la publicación debe contener al menos 5 caracteres.',
            'motivo.max' => 'El motivo de la publicación no debe superar los 500 caracteres.',
        ];
    }
}
