<?php

namespace App\Services;

use App\Models\Jugador;
use App\Models\Persona;
use App\Models\User;

class VinculacionJugadorService
{
    public function __construct(private AuditService $auditoria)
    {
    }

    public function vincular(User $usuario, string $curp): void
    {
        $this->auditoria->conMotivo('Vinculación de cuenta con jugador mediante CURP', function () use ($usuario, $curp): void {
            if ($usuario->persona_id !== null) {
                return;
            }

            $persona = Persona::query()
                ->whereRaw('UPPER(curp) = ?', [strtoupper($curp)])
                ->whereHas('inscripciones')
                ->first();

            if ($persona === null) {
                return;
            }

            // El índice unique de usuarios.persona_id resuelve de forma segura intentos concurrentes.
            $usuario->forceFill(['persona_id' => $persona->getKey()])->save();
            $this->auditoria->registrar('jugador_vinculado_curp', $usuario, null, [
                'persona_id' => $persona->getKey(),
            ]);
        });
    }

    public function desvincular(Jugador $jugador): void
    {
        $personaId = $jugador->persona_id;

        $this->auditoria->conMotivo('Desvinculación administrativa de cuenta de jugador', function () use ($personaId): void {
            $usuarios = User::query()->where('persona_id', $personaId)->get();

            foreach ($usuarios as $usuario) {
                $usuario->forceFill(['persona_id' => null])->save();
                $this->auditoria->registrar('jugador_desvinculado', $usuario, ['persona_id' => $personaId], ['persona_id' => null]);
            }
        });
    }
}
