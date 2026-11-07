<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class VinculacionJugadorService
{
    public function __construct(private AuditService $auditoria)
    {
    }

    /**
     * Vincula una cuenta únicamente con una identidad que ya tenga el rol deportivo jugador
     * y que todavía no esté asociada a otra cuenta.
     */
    public function vincularPorCurp(User $cuenta, string $curp): void
    {
        $this->auditoria->conMotivo('Vinculación de cuenta Jugador mediante CURP (HU-016)', function () use ($cuenta, $curp): void {
            $cuentaBloqueada = User::query()
                ->whereKey($cuenta->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($cuentaBloqueada->persona_id !== null) {
                return;
            }

            $persona = Persona::query()
                ->whereRaw('UPPER(curp) = ?', [strtoupper(trim($curp))])
                ->whereHas('roles', fn (Builder $consulta): Builder => $consulta->where('rol', 'jugador'))
                ->whereDoesntHave('usuario')
                ->lockForUpdate()
                ->first();

            if ($persona === null) {
                return;
            }

            $cuentaBloqueada->forceFill(['persona_id' => $persona->getKey()])->save();

            // No se registra la CURP en auditoría. El identificador de persona basta para rastrear el vínculo.
            $this->auditoria->registrar('jugador_vinculado_curp', $cuentaBloqueada, null, [
                'persona_id' => $persona->getKey(),
            ]);
        });
    }

    public function desvincular(User $cuentaJugador): void
    {
        $this->auditoria->conMotivo('Desvinculación administrativa de cuenta Jugador (HU-016)', function () use ($cuentaJugador): void {
            $cuentaBloqueada = User::query()
                ->whereKey($cuentaJugador->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $personaId = $cuentaBloqueada->persona_id;

            if ($personaId === null) {
                return;
            }

            $rolAnterior = $cuentaBloqueada->rol;
            $rolSpatieRevocado = $cuentaBloqueada->hasRole('Jugador');

            $atributos = ['persona_id' => null];

            if ($rolAnterior === 'Jugador') {
                $atributos['rol'] = 'Consultor';
            }

            $cuentaBloqueada->forceFill($atributos)->save();

            if ($rolSpatieRevocado) {
                $cuentaBloqueada->removeRole('Jugador');
            }

            $this->auditoria->registrar('jugador_desvinculado', $cuentaBloqueada, [
                'persona_id' => $personaId,
                'rol' => $rolAnterior,
                'rol_spatie' => $rolSpatieRevocado ? 'Jugador' : null,
            ], [
                'persona_id' => null,
                'rol' => $atributos['rol'] ?? $rolAnterior,
                'rol_spatie' => null,
            ]);
        });
    }
}
