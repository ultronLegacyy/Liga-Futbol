<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

// HU-082 (criterio 4): reinicio del segundo factor por el Administrador General, con motivo y en la bitácora
class SegundoFactorService
{
    public function __construct(private readonly AuditService $auditoria)
    {
    }

    /**
     * Borra el secreto, los códigos de recuperación y la confirmación: la cuenta vuelve a la pantalla de activación en
     * su siguiente petición. La auditoría omite el secreto y los códigos (atributos ocultos de User).
     */
    public function reiniciar(User $usuario, string $motivo): void
    {
        $this->auditoria->conMotivo($motivo, function () use ($usuario): void {
            $bloqueado = User::query()->lockForUpdate()->findOrFail($usuario->id);

            if ($bloqueado->two_factor_secret === null) {
                throw ValidationException::withMessages([
                    'motivo' => 'Esta cuenta no tiene segundo factor que reiniciar.',
                ]);
            }

            $bloqueado->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
        });
    }
}
