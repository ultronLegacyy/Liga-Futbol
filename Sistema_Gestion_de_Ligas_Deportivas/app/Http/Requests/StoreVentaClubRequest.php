<?php

namespace App\Http\Requests;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVentaClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        $club = $this->route('club');

        return $club instanceof Club && $this->user()?->can('transferOwnership', $club) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'propietario_usuario_id' => ['required', 'integer', 'exists:usuarios,id'],
            'carta_venta_pdf' => ['nullable', 'string', 'max:255'],
            'fecha_venta' => ['required', 'date'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $club = $this->route('club');

            $propietarioActualId = $club instanceof Club
                ? ($club->propietario_usuario_id ?? $club->usuarios()->value('id'))
                : null;

            if ($propietarioActualId !== null
                && $this->filled('propietario_usuario_id')
                && (int) $this->input('propietario_usuario_id') === (int) $propietarioActualId) {
                $validator->errors()->add('propietario_usuario_id', 'El nuevo propietario debe ser distinto al propietario actual.');
            }
        }];
    }
}
