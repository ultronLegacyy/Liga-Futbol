<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Jugador;

class RegistroJugadorService
{
    public function __construct(
        private ReglaSaltoCategoriaService $reglaSaltoCategoria,
        private AuditService $auditoria,
    ) {
    }

    /** @param array{persona_id: int|string, dorsal?: int|string|null} $datos */
    public function registrar(Equipo $equipoDestino, array $datos): Jugador
    {
        return $this->auditoria->conMotivo('Inscripción de jugador en equipo', function () use ($equipoDestino, $datos): Jugador {
            $inscripcionAnterior = Jugador::query()
                ->where('persona_id', $datos['persona_id'])
                ->with('equipo.grupo.categoria')
                ->latest('id')
                ->first();

            if ($inscripcionAnterior !== null) {
                $equipoOrigen = $inscripcionAnterior->equipo;
                $categoriaOrigen = $equipoOrigen->grupo->categoria;
                $categoriaDestino = $equipoDestino->grupo->categoria;

                $this->reglaSaltoCategoria->validar(
                    $categoriaOrigen,
                    $categoriaDestino,
                );
            }

            return $equipoDestino->jugadores()->create($datos);
        });
    }
}
