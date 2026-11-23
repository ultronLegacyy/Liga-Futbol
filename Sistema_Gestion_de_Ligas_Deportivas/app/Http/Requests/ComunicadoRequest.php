<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DescartaArreglosEnCamposDeTexto;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

// Comunicado por correo a un grupo de usuarios (HU-066, RF-15.3): la revisión de destinatarios y el envío
class ComunicadoRequest extends FormRequest
{
    use DescartaArreglosEnCamposDeTexto;

    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('comunicados.enviar');
    }

    // El 403 en español aunque la ruta llegue sin el middleware del permiso
    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('No posee los permisos requeridos para enviar comunicados.');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'asunto' => ['bail', 'required', 'string', 'min:3', 'max:200'],
            'mensaje' => ['bail', 'required', 'string', 'min:5', 'max:10000'],
            'roles' => ['bail', 'required', 'array', 'min:1'],
            'roles.*' => ['bail', 'string', 'distinct', Rule::in(RoleAndPermissionSeeder::ROLES)],
            'categoria_id' => ['bail', 'nullable', 'integer', 'exists:categorias,id'],
            'clubes' => ['bail', 'nullable', 'array'],
            'clubes.*' => ['bail', 'integer', 'distinct', 'exists:clubes,id'],
            // Solo el envío lo exige: viene de la revisión y evita mandar dos veces el mismo comunicado
            'token' => ['bail', $this->routeIs('comunicados.store') ? 'required' : 'nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'asunto.required' => 'El asunto del comunicado es obligatorio.',
            'asunto.string' => 'El asunto del comunicado debe ser texto.',
            'asunto.min' => 'El asunto del comunicado debe tener al menos :min caracteres.',
            'asunto.max' => 'El asunto del comunicado no debe tener más de :max caracteres.',
            'mensaje.required' => 'El mensaje del comunicado es obligatorio.',
            'mensaje.string' => 'El mensaje del comunicado debe ser texto.',
            'mensaje.min' => 'El mensaje del comunicado debe tener al menos :min caracteres.',
            'mensaje.max' => 'El mensaje del comunicado no debe tener más de :max caracteres.',
            'roles.required' => 'Elige al menos un rol destinatario.',
            'roles.array' => 'Los roles destinatarios no son válidos.',
            'roles.min' => 'Elige al menos un rol destinatario.',
            'roles.*.string' => 'Uno de los roles elegidos no es válido.',
            'roles.*.distinct' => 'Un rol está repetido.',
            'roles.*.in' => 'Uno de los roles elegidos no existe.',
            'categoria_id.integer' => 'La categoría elegida no es válida.',
            'categoria_id.exists' => 'La categoría elegida no existe.',
            'clubes.array' => 'Los clubes elegidos no son válidos.',
            'clubes.*.integer' => 'Uno de los clubes elegidos no es válido.',
            'clubes.*.distinct' => 'Un club está repetido.',
            'clubes.*.exists' => 'Uno de los clubes elegidos no existe.',
            'token.required' => 'Falta la confirmación del envío: revisa los destinatarios antes de enviar.',
            'token.uuid' => 'La confirmación del envío no es válida: revisa los destinatarios otra vez.',
        ];
    }

    /**
     * Filtros del grupo, como se guardan en el comunicado.
     *
     * @return array{roles: list<string>, categoria_id: int|null, clubes: list<int>}
     */
    public function filtros(): array
    {
        return [
            'roles' => array_values(array_map('strval', (array) $this->validated('roles'))),
            'categoria_id' => $this->filled('categoria_id') ? $this->integer('categoria_id') : null,
            'clubes' => array_values(array_map('intval', (array) $this->validated('clubes', []))),
        ];
    }

    /** @return list<string> */
    protected function camposDeTexto(): array
    {
        return ['asunto', 'mensaje', 'token'];
    }
}
