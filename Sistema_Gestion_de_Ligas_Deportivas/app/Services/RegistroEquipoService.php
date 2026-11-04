<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RegistroEquipoService
{
    public function __construct(private AuditService $auditoria)
    {
    }

    /** @param array{temporada_id: int|string, grupo_id: int|string, nombre: string, club_id?: int|string} $datos */
    public function registrar(User $usuario, array $datos): Equipo
    {
        return $this->auditoria->conMotivo('Registro de equipo deportivo', function () use ($usuario, $datos): Equipo {
            $clubId = $usuario->hasRole('Administrador General')
                ? ($datos['club_id'] ?? null)
                : $usuario->club_id;

            if ($clubId === null) {
                throw ValidationException::withMessages([
                    'club_id' => 'Debe seleccionar el club al que se asignará el equipo.',
                ]);
            }

            $club = Club::query()->findOrFail($clubId);

            return $club->equipos()->create([
                'temporada_id' => $datos['temporada_id'],
                'grupo_id' => $datos['grupo_id'],
                'nombre' => $datos['nombre'],
            ]);
        });
    }
}
