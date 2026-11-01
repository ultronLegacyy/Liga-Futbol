<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class RNF26Password implements ValidationRule
{
    /**
     * Cache estática en memoria del diccionario de contraseñas comprometidas.
     *
     * @var array<string, bool>|null
     */
    protected static ?array $compromisedCache = null;

    /**
     * Contraseñas adicionales agregadas dinámicamente durante pruebas.
     *
     * @var array<string, bool>
     */
    protected static array $customCompromised = [];

    /**
     * Modelo de usuario opcional para verificar estatus de doble factor (2FA).
     *
     * @var mixed
     */
    protected mixed $user;

    /**
     * Opcionalmente recibe el usuario para evaluar si tiene 2FA activo.
     */
    public function __construct(mixed $user = null)
    {
        $this->user = $user;
    }

    /**
     * Ejecutar la regla de validación conforme a RNF-26.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $passwordString = (string) $value;
        $length = Str::length($passwordString);

        // Límite máximo establecido por RNF-26
        if ($length > 64) {
            $fail('La contraseña no debe exceder los 64 caracteres.');

            return;
        }

        // Verificamos si el usuario fue pasado y si tiene el secreto 2FA configurado
        $hasTwoFactor = false;
        if ($this->user) {
            if (method_exists($this->user, 'hasEnabledTwoFactorAuthentication')) {
                $hasTwoFactor = $this->user->hasEnabledTwoFactorAuthentication() || ! empty($this->user->two_factor_secret);
            } else {
                $hasTwoFactor = ! empty($this->user->two_factor_secret) || ! empty($this->user->two_factor_confirmed_at);
            }
        }

        if ($hasTwoFactor) {
            if ($length < 8) {
                $fail('Como tienes doble factor activo, la contraseña debe tener al menos 8 caracteres.');

                return;
            }
        } else {
            if ($length < 15) {
                $fail('La contraseña debe tener al menos 15 caracteres.');

                return;
            }
        }

        // Verificación offline contra catálogo local de contraseñas comprometidas (RNF-26 sección 1.2)
        if (self::isCompromised($passwordString)) {
            $fail('Esta contraseña ha aparecido en filtraciones de seguridad conocidas. Elige una más segura.');

            return;
        }
    }

    /**
     * Determina de manera offline si una contraseña está en el catálogo de filtraciones conocidas.
     */
    public static function isCompromised(string $password): bool
    {
        $normalized = Str::lower(trim($password));

        if ($normalized === '') {
            return false;
        }

        if (isset(self::$customCompromised[$normalized])) {
            return true;
        }

        if (self::$compromisedCache === null) {
            self::$compromisedCache = self::loadDictionary();
        }

        return isset(self::$compromisedCache[$normalized]);
    }

    /**
     * Carga el diccionario offline desde disco con fallback integrado.
     *
     * @return array<string, bool>
     */
    protected static function loadDictionary(): array
    {
        $dictionary = [];

        // Catálogo básico embebido en código para garantizar funcionamiento inmediato
        $builtin = [
            '123456',
            '12345678',
            '123456789012345',
            '1234567890123456',
            'password',
            'passwordpassword',
            'qwertyuiopasdfg',
            'qwertyuiopasdfgh',
            'admin1234567890',
            'adminadminadmin',
            'administrador123',
            'contrasenasegura',
            'cambiame12345678',
            'supersecretpassword',
            'secretpassword123',
            'correcthorsebatterystaple',
            'iloveyouiloveyou',
            'temporaltemporaltemporal',
            'welcomewelcomewelcome',
            'letmeinletmeinletmein',
            'ligadeportiva2026',
            'sistema_gestion_ligas',
        ];

        foreach ($builtin as $item) {
            $dictionary[Str::lower($item)] = true;
        }

        // Lectura de archivos locales si existen
        $candidates = [
            resource_path('data/compromised_passwords.txt'),
            storage_path('app/security/compromised_passwords.txt'),
        ];

        foreach ($candidates as $filePath) {
            if (is_file($filePath) && is_readable($filePath)) {
                $content = @file_get_contents($filePath);
                if ($content !== false) {
                    $lines = preg_split('/\r\n|\r|\n/', $content);
                    if (is_array($lines)) {
                        foreach ($lines as $line) {
                            $clean = Str::lower(trim($line));
                            if ($clean !== '') {
                                $dictionary[$clean] = true;
                            }
                        }
                    }
                }
            }
        }

        return $dictionary;
    }

    /**
     * Permite agregar contraseñas dinámicas durante pruebas automatizadas.
     */
    public static function addCustomCompromised(string $password): void
    {
        self::$customCompromised[Str::lower(trim($password))] = true;
    }

    /**
     * Restablece la memoria caché del diccionario.
     */
    public static function resetDictionaryCache(): void
    {
        self::$compromisedCache = null;
        self::$customCompromised = [];
    }
}
