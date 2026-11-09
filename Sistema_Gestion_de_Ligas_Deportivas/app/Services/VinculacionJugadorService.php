<?php

namespace App\Services;

use App\Models\Jugador;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

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
                ->whereHas('roles', fn ($query) => $query->where('rol', 'jugador'))
                ->first();

            if ($persona === null) {
                return;
            }

            if (User::query()->where('persona_id', $persona->getKey())->exists()) {
                return;
            }

            try {
                $usuario->forceFill(['persona_id' => $persona->getKey()])->save();
            } catch (UniqueConstraintViolationException) {
                // Otra solicitud pudo vincular la persona después de la consulta anterior.
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
                $usuario->forceFill(['persona_id' => null, 'rol' => 'Consultor'])->save();
                $usuario->removeRole('Jugador');
                $this->auditoria->registrar('jugador_desvinculado', $usuario, ['persona_id' => $personaId], ['persona_id' => null]);
            }
        });
    }
}
