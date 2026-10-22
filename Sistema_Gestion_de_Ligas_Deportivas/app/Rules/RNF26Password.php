<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class RNF26Password implements ValidationRule
{
    protected $user;

    /**
     * Opcionalmente recibe el usuario para evaluar si tiene 2FA activo.
     */
    public function __construct($user = null)
    {
        $this->user = $user;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $length = Str::length($value);

        // Límite máximo establecido por RNF-26
        if ($length > 64) {
            $fail('La contraseña no debe exceder los 64 caracteres.');
            return;
        }

        // Verificamos si el usuario fue pasado y si tiene el secreto 2FA configurado
        $hasTwoFactor = $this->user && !empty($this->user->two_factor_secret);

        if ($hasTwoFactor) {
            if ($length < 8) {
                $fail('Como tienes doble factor activo, la contraseña debe tener al menos 8 caracteres.');
            }
        } else {
            if ($length < 15) {
                $fail('La contraseña debe tener al menos 15 caracteres.');
            }
        }
    }
}