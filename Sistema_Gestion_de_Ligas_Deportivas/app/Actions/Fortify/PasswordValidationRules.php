<?php

namespace App\Actions\Fortify;

use App\Rules\RNF26Password;

trait PasswordValidationRules
{
    /**
     * Obtener las reglas de validación de contraseñas conforme a RNF-26.
     *
     * @param  mixed  $user
     * @return array<int, mixed>
     */
    protected function passwordRules(mixed $user = null): array
    {
        return ['required', 'string', new RNF26Password($user), 'confirmed'];
    }
}
