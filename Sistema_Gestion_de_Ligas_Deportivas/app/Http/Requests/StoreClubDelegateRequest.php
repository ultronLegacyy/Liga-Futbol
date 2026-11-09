<?php

namespace App\Http\Requests;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;

class StoreClubDelegateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $club = $this->route('club');

        return $club instanceof Club && $this->user()?->can('assignDelegates', $club) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'usuario_id' => ['required', 'integer', 'exists:usuarios,id'],
        ];
    }
}
