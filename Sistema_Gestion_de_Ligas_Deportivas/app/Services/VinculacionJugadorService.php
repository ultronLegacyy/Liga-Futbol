<?php

namespace App\Services;

use App\Models\Jugador;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;

class VinculacionJugadorService
{
    private const MAX_INTENTOS_FALLIDOS = 5;
    private const BLOQUEO_SEGUNDOS = 900;

    public function __construct(private AuditService $auditoria)
    {
    }

    private function claveIntentos(User $usuario): string
    {
        return 'jugador_vinculacion:intentos:'.$usuario->getKey();
    }

    private function claveBloqueo(User $usuario): string
    {
        return 'jugador_vinculacion:bloqueo:'.$usuario->getKey();
    }

    private function registrarFallo(User $usuario): void
    {
        $intentos = RateLimiter::hit(
            $this->claveIntentos($usuario),
            self::BLOQUEO_SEGUNDOS
        );

        if ($intentos >= self::MAX_INTENTOS_FALLIDOS) {
            RateLimiter::hit(
                $this->claveBloqueo($usuario),
                self::BLOQUEO_SEGUNDOS
            );
        }
    }

    public function vincular(User $usuario, string $curp): void
    {
        $this->auditoria->conMotivo('Vinculación de cuenta con jugador mediante CURP', function () use ($usuario, $curp): void {
            if ($usuario->persona_id !== null) {
                return;
            }

            if (RateLimiter::tooManyAttempts($this->claveBloqueo($usuario), 1)) {
                return;
            }

            $persona = Persona::query()
                ->whereRaw('UPPER(curp) = ?', [strtoupper($curp)])
                ->whereHas('roles', fn ($query) => $query->where('rol', 'jugador'))
                ->first();

            if ($persona === null) {
                $this->registrarFallo($usuario);
                return;
            }

            if (User::query()->where('persona_id', $persona->getKey())->exists()) {
                $this->registrarFallo($usuario);
                return;
            }

            try {
                $usuario->forceFill(['persona_id' => $persona->getKey()])->save();
                RateLimiter::clear($this->claveIntentos($usuario));
            } catch (UniqueConstraintViolationException) {
                // Otra solicitud pudo vincular la persona después de la consulta anterior.
                $this->registrarFallo($usuario);
                return;
            }

            $this->auditoria->registrar('jugador_vinculado_curp', $usuario, null, [
                'persona_id' => $persona->getKey(),
            ]);
        });
    }

    public function desvincular(Jugador $jugador): void
    {
        $this->desvincularPersona((int) $jugador->persona_id);
    }

    public function desvincularCuenta(User $usuario): void
    {
        if ($usuario->persona_id !== null) {
            $this->desvincularPersona((int) $usuario->persona_id);
        }
    }

    private function desvincularPersona(int $personaId): void
    {
        $this->auditoria->conMotivo('Desvinculación administrativa de cuenta de jugador', function () use ($personaId): void {
            $usuarios = User::query()->where('persona_id', $personaId)->get();

            foreach ($usuarios as $usuario) {
                $usuario->forceFill(['persona_id' => null])->save();
                $this->auditoria->registrar('jugador_desvinculado', $usuario, ['persona_id' => $personaId], ['persona_id' => null]);
            }
        });
    }
}
