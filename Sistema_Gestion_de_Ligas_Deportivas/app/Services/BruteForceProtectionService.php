<?php

namespace App\Services;

use App\Mail\AvisoCuentaBloqueada;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class BruteForceProtectionService
{
    /**
     * Número máximo de intentos fallidos antes de bloquear la cuenta (RNF-28).
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Duración del bloqueo en segundos (15 minutos = 900 segundos) (RNF-28).
     */
    public const LOCKOUT_SECONDS = 900;

    /**
     * Duración del bloqueo en minutos.
     */
    public const LOCKOUT_MINUTES = 15;

    /**
     * Normaliza el identificador de cuenta (correo electrónico).
     */
    public function normalizeKey(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Clave para contabilizar intentos fallidos.
     */
    public function attemptsKey(string $email): string
    {
        return 'brute_force:attempts:'.$this->normalizeKey($email);
    }

    /**
     * Clave para marcar el bloqueo temporal activo.
     */
    public function lockoutKey(string $email): string
    {
        return 'brute_force:lockout:'.$this->normalizeKey($email);
    }

    /**
     * Determina si la cuenta se encuentra actualmente bloqueada por fuerza bruta.
     */
    public function isLocked(string $email): bool
    {
        $lockKey = $this->lockoutKey($email);

        if (RateLimiter::attempts($lockKey) > 0) {
            $availableIn = RateLimiter::availableIn($lockKey);
            if ($availableIn > 0) {
                return true;
            }

            // Si el tiempo de bloqueo ya expiró (>= 15 minutos), se liberan automáticamente
            // los bloqueos e intentos previos (RNF-28)
            $this->clearAttempts($email);
        }

        return false;
    }

    /**
     * Obtiene los segundos restantes del bloqueo temporal activo.
     */
    public function getLockoutRemainingSeconds(string $email): int
    {
        return RateLimiter::availableIn($this->lockoutKey($email));
    }

    /**
     * Obtiene los minutos restantes redondeados hacia arriba (mínimo 1).
     */
    public function getLockoutRemainingMinutes(string $email): int
    {
        $seconds = $this->getLockoutRemainingSeconds($email);

        return max(1, (int) ceil($seconds / 60));
    }

    /**
     * Registra un intento fallido para la cuenta.
     * Si alcanza 5 intentos, activa el bloqueo de 15 minutos, envía correo y dispara Lockout.
     *
     * @return int Número de intentos registrados
     */
    public function recordFailedAttempt(string $email, string $ip, ?Request $request = null): int
    {
        $attemptsKey = $this->attemptsKey($email);
        $lockKey = $this->lockoutKey($email);

        $attempts = RateLimiter::hit($attemptsKey, self::LOCKOUT_SECONDS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Activar bloqueo estricto por 15 minutos
            RateLimiter::hit($lockKey, self::LOCKOUT_SECONDS);

            // Disparar evento oficial de Laravel para auditorías de seguridad
            if ($request) {
                event(new Lockout($request));
            }

            // Enviar notificación inmediata por correo al titular de la cuenta si existe
            $this->sendLockoutNotification($email, $ip);
        }

        return $attempts;
    }

    /**
     * Envía correo de alerta al titular de la cuenta cuando se bloquea por 5 intentos fallidos.
     */
    protected function sendLockoutNotification(string $email, string $ip): void
    {
        $user = User::where('email', $this->normalizeKey($email))->first();

        if ($user && ! empty($user->email)) {
            $ahora = Carbon::now()->format('d/m/Y H:i:s');

            Mail::to($user->email)->send(new AvisoCuentaBloqueada(
                email: $user->email,
                ip: $ip,
                fechaHora: $ahora,
                duracionMinutos: self::LOCKOUT_MINUTES,
                nombre: $user->name,
            ));
        }
    }

    /**
     * Obtiene la cantidad actual de intentos fallidos registrados.
     */
    public function getAttempts(string $email): int
    {
        return RateLimiter::attempts($this->attemptsKey($email));
    }

    /**
     * Restablece el contador de intentos fallidos y el bloqueo de la cuenta.
     */
    public function clearAttempts(string $email): void
    {
        RateLimiter::clear($this->attemptsKey($email));
        RateLimiter::clear($this->lockoutKey($email));
    }
}
