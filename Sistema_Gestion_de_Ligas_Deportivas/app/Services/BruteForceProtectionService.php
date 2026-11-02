<?php

namespace App\Services;

use App\Mail\CorreoDelSistema;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

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
     * Resuelve el objetivo canónico (User|string) para evitar evasiones por collation en BD (RNF-28).
     * En MariaDB utf8mb4_unicode_ci, admin@liga.com, ADMIN@LIGA.COM y admín@liga.com apuntan al mismo usuario.
     */
    public function resolveTarget(User|string $target): User|string
    {
        if ($target instanceof User) {
            return $target;
        }

        $trimmed = trim($target);
        if ($trimmed === '') {
            return '';
        }

        $user = User::where('email', $trimmed)->first();
        if ($user) {
            return $user;
        }

        return $this->normalizeKey($trimmed);
    }

    /**
     * Clave canónica del objetivo para RateLimiter.
     * Si el usuario existe en BD: user:{id}
     * Si no existe en BD: email:{normalized_email}
     */
    public function targetKey(User|string $target): string
    {
        $resolved = $this->resolveTarget($target);

        if ($resolved instanceof User) {
            return 'user:'.$resolved->id;
        }

        return 'email:'.$resolved;
    }

    /**
     * Clave para contabilizar intentos fallidos.
     */
    public function attemptsKey(User|string $target): string
    {
        return 'brute_force:attempts:'.$this->targetKey($target);
    }

    /**
     * Clave para marcar el bloqueo temporal activo.
     */
    public function lockoutKey(User|string $target): string
    {
        return 'brute_force:lockout:'.$this->targetKey($target);
    }

    /**
     * Determina si la cuenta se encuentra actualmente bloqueada por fuerza bruta.
     */
    public function isLocked(User|string $target): bool
    {
        $lockKey = $this->lockoutKey($target);

        if (RateLimiter::attempts($lockKey) > 0) {
            $availableIn = RateLimiter::availableIn($lockKey);
            if ($availableIn > 0) {
                return true;
            }

            // Si el tiempo de bloqueo ya expiró (>= 15 minutos), se liberan automáticamente
            // los bloqueos e intentos previos (RNF-28)
            $this->clearAttempts($target);
        }

        return false;
    }

    /**
     * Obtiene los segundos restantes del bloqueo temporal activo.
     */
    public function getLockoutRemainingSeconds(User|string $target): int
    {
        return RateLimiter::availableIn($this->lockoutKey($target));
    }

    /**
     * Obtiene los minutos restantes redondeados hacia arriba (mínimo 1).
     */
    public function getLockoutRemainingMinutes(User|string $target): int
    {
        $seconds = $this->getLockoutRemainingSeconds($target);

        return max(1, (int) ceil($seconds / 60));
    }

    /**
     * Registra un intento fallido para la cuenta.
     * Si alcanza 5 intentos, activa el bloqueo de 15 minutos, envía correo y dispara Lockout.
     *
     * @return int Número de intentos registrados
     */
    public function recordFailedAttempt(User|string $target, string $ip, ?Request $request = null): int
    {
        $attemptsKey = $this->attemptsKey($target);
        $lockKey = $this->lockoutKey($target);

        $attempts = RateLimiter::hit($attemptsKey, self::LOCKOUT_SECONDS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Activar bloqueo estricto por 15 minutos
            RateLimiter::hit($lockKey, self::LOCKOUT_SECONDS);

            // Disparar evento oficial de Laravel para auditorías de seguridad
            if ($request) {
                event(new Lockout($request));
            }
        }

        // Enviar notificación inmediata por correo ÚNICAMENTE en el intento que detona el bloqueo (intento 5)
        if ($attempts === self::MAX_ATTEMPTS) {
            $this->sendLockoutNotification($target, $ip);
        }

        return $attempts;
    }

    /**
     * Envía correo de alerta al titular de la cuenta cuando se bloquea por 5 intentos fallidos.
     * Maneja fallos de transporte SMTP de manera silenciosa para evitar timing attacks / user enumeration oracles.
     */
    public function sendLockoutNotification(User|string $target, string $ip): void
    {
        $resolved = $this->resolveTarget($target);
        $user = $resolved instanceof User ? $resolved : null;

        if ($user && ! empty($user->email)) {
            try {
                $fechaHora = now()->setTimezone('America/Mexico_City')->format('d/m/Y H:i:s');
                $duracion = self::LOCKOUT_MINUTES;

                $mensaje = "Hola {$user->name},\n\n"
                    ."Se ha bloqueado temporalmente el acceso a tu cuenta por un período de {$duracion} minutos "
                    ."debido a que se superó el límite de ".self::MAX_ATTEMPTS." intentos fallidos consecutivos de inicio de sesión.\n\n"
                    ."Detalles del evento de seguridad:\n"
                    ."- Dirección IP de origen: {$ip}\n"
                    ."- Fecha y hora: {$fechaHora} (Hora del Centro de México)\n"
                    ."- Duración del bloqueo: {$duracion} minutos\n\n"
                    ."Si fuiste tú y olvidaste tu contraseña, por favor espera a que concluya el período de bloqueo o utiliza la opción de recuperación de contraseña.\n\n"
                    ."Si no reconoces estos intentos, te recomendamos cambiar tu contraseña inmediatamente una vez concluido el período de bloqueo y activar la autenticación de dos factores (2FA) para proteger tu cuenta.";

                Mail::to($user->email)->send(new CorreoDelSistema(
                    asunto: 'Aviso de seguridad: Cuenta bloqueada temporalmente por intentos fallidos',
                    mensaje: $mensaje,
                ));
            } catch (Throwable $e) {
                Log::error('Falló el envío del aviso de cuenta bloqueada.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Obtiene la cantidad actual de intentos fallidos registrados.
     */
    public function getAttempts(User|string $target): int
    {
        return RateLimiter::attempts($this->attemptsKey($target));
    }

    /**
     * Restablece el contador de intentos fallidos y el bloqueo de la cuenta.
     */
    public function clearAttempts(User|string $target): void
    {
        RateLimiter::clear($this->attemptsKey($target));
        RateLimiter::clear($this->lockoutKey($target));

        // Si es un User o se resuelve como tal, también limpiamos la clave de email por si hubo intentos previos
        $resolved = $this->resolveTarget($target);
        if ($resolved instanceof User && ! empty($resolved->email)) {
            RateLimiter::clear('brute_force:attempts:email:'.$this->normalizeKey($resolved->email));
            RateLimiter::clear('brute_force:lockout:email:'.$this->normalizeKey($resolved->email));
        }
    }
}
