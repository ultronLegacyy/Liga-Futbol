<?php

namespace App\Http\Requests;

use App\Services\PermisosRolService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// HU-083: edición de los permisos de un rol. Solo el Administrador General (CA3, RNF-29);
// la ruta ya está en su grupo, y aquí se vuelve a revisar por si alguien la mueve
class ActualizarPermisosRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('Administrador General');
    }

    // El 403 sale en español, no con el «This action is unauthorized.» de Laravel
    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Solo el Administrador General puede cambiar los permisos de un rol.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Sin ninguna casilla marcada el rol se queda sin permisos; por eso el arreglo puede faltar
            // bail: un solo mensaje por campo. Los repetidos no se rechazan: el servicio los junta en uno
            'permisos' => ['bail', 'sometimes', 'array'],
            'permisos.*' => ['bail', 'string', Rule::in(app(PermisosRolService::class)->catalogo())],
            'motivo' => ['bail', 'required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permisos.array' => 'Los permisos enviados no son válidos.',
            'permisos.*.string' => 'Uno de los permisos enviados no es válido.',
            'permisos.*.in' => 'El permiso «:input» no existe en el catálogo.',
            'motivo.required' => 'Escribe el motivo del cambio; quedará en la bitácora.',
            'motivo.string' => 'El motivo del cambio no es válido.',
            'motivo.min' => 'El motivo debe tener al menos 3 caracteres.',
            'motivo.max' => 'El motivo no debe superar los 1000 caracteres.',
        ];
    }

    /**
     * @return list<string>
     */
    public function permisos(): array
    {
        return array_values(array_map(strval(...), (array) $this->validated('permisos', [])));
    }
}
