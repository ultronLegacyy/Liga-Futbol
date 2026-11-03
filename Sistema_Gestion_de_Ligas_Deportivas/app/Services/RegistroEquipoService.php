<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistroEquipoService
{
    /** @param array{temporada_id: int|string, grupo_id: int|string, nombre: string} $datos */
    public function registrar(User $delegado, array $datos): Equipo
    {
        return DB::transaction(function () use ($delegado, $datos): Equipo {
            $club = $delegado->club()->firstOrFail();

            return $club->equipos()->create([
                'temporada_id' => $datos['temporada_id'],
                'grupo_id' => $datos['grupo_id'],
                'nombre' => $datos['nombre'],
            ]);
        });
    }
}
