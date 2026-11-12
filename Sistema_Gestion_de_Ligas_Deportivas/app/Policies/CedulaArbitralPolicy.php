<?php

namespace App\Policies;

use App\Models\Partido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Cédula arbitral de un partido (HU-036, RF-07.1). El Árbitro en Campo solo accede a los partidos que tiene asignados (RNF-29)
class CedulaArbitralPolicy
{
    public function before(User $usuario, string $habilidad): ?bool
    {
        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Consultar la cédula de un partido
    public function ver(User $usuario, Partido $partido): Response
    {
        if (! $usuario->can('cedulas.ver')) {
            return Response::deny('No cuenta con privilegios para consultar cédulas arbitrales.');
        }

        return $this->accesoDelArbitro($usuario, $partido);
    }

    // Abrir la cédula y capturar alineación, marcador, eventos e incidencias
    public function capturar(User $usuario, Partido $partido): Response
    {
        if (! $usuario->can('cedulas.capturar')) {
            return Response::deny('No cuenta con privilegios para capturar cédulas arbitrales.');
        }

        return $this->accesoDelArbitro($usuario, $partido);
    }

    /**
     * El Árbitro en Campo solo entra a los partidos donde es el árbitro asignado; Arbitro y User se enlazan por persona_id.
     * Los demás roles con el permiso (Capturista, Comisión Arbitral y otros) acceden a todas, como pide HU-037.
     */
    private function accesoDelArbitro(User $usuario, Partido $partido): Response
    {
        if (! $usuario->hasRole('Árbitro en Campo')) {
            return Response::allow();
        }

        $arbitro = $partido->arbitro()->first(['id', 'persona_id']);

        return $usuario->persona_id !== null && $arbitro !== null && $arbitro->persona_id === $usuario->persona_id
            ? Response::allow()
            : Response::deny('Solo el árbitro asignado a este partido puede acceder a su cédula.');
    }
}
