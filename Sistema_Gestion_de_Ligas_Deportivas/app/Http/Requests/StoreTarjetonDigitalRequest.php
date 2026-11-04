<?php

namespace App\Http\Requests;

use App\Models\Club;
use App\Models\TarjetonDigital;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreTarjetonDigitalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $club = $this->route('club');
        $temporada = $this->route('temporada');
        $user = $this->user();

        return $club instanceof Club
            && $temporada instanceof Temporada
            && $user instanceof User
            && $user->can('create', [TarjetonDigital::class, $club, $temporada]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
