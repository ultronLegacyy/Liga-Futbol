<?php

namespace App\Policies;

use App\Models\Partido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

// Cédula arbitral de un partido (HU-036, RF-07.1). El Árbitro en Campo solo accede a los partidos que tiene asignados (RNF-29)
class CedulaArbitralPolicy
{
    private const ROL_ARBITRO = 'Árbitro en Campo';

    /**
     * El Administrador General pasa en todo, salvo en abrir, firmar y cerrar: le tocan al árbitro asignado
     * y la matriz no le da cedulas.firmar (HU-075).
     */
    public function before(User $usuario, string $habilidad): ?bool
    {
        if (in_array($habilidad, ['abrir', 'firmar', 'cerrar'], true)) {
            return null;
        }

        if ($usuario->hasRole('Administrador General')) {
            return true;
        }

        return null;
    }

    // Lista de los partidos asignados al árbitro que inició sesión
    public function misPartidos(User $usuario): Response
    {
        return $usuario->hasRole(self::ROL_ARBITRO) && $usuario->can('cedulas.capturar')
            ? Response::allow()
            : Response::deny('Esta lista es solo para el Árbitro en Campo.');
    }

    // Consultar la cédula de un partido
    public function ver(User $usuario, Partido $partido): Response
    {
        if (! $usuario->can('cedulas.ver')) {
            return Response::deny('No cuenta con privilegios para consultar cédulas arbitrales.');
        }

        return $this->accesoDelArbitro($usuario, $partido);
    }

    // Abrir la cédula: solo el Árbitro en Campo asignado al partido, con una acción explícita (POST)
    public function abrir(User $usuario, Partido $partido): Response
    {
        if (! $usuario->hasRole(self::ROL_ARBITRO) || ! $usuario->can('cedulas.capturar')) {
            return Response::deny('Solo el árbitro asignado a este partido puede abrir su cédula.');
        }

        return $this->accesoDelArbitro($usuario, $partido);
    }

    // Capturar alineación, marcador, eventos e incidencias en una cédula ya abierta
    public function capturar(User $usuario, Partido $partido): Response
    {
        if (! $usuario->can('cedulas.capturar')) {
            return Response::deny('No cuenta con privilegios para capturar cédulas arbitrales.');
        }

        return $this->accesoDelArbitro($usuario, $partido);
    }

    // HU-075: recabar la firma de los capitanes; solo el árbitro asignado, con cedulas.firmar
    public function firmar(User $usuario, Partido $partido): Response
    {
        if (! $usuario->can('cedulas.firmar')) {
            return Response::deny('No cuenta con privilegios para recabar firmas ni cerrar cédulas arbitrales.');
        }

        return $this->accesoDelArbitro($usuario, $partido);
    }

    // HU-075: cerrar la cédula; lo mismo que firmar
    public function cerrar(User $usuario, Partido $partido): Response
    {
        return $this->firmar($usuario, $partido);
    }

    /**
     * El Árbitro en Campo solo entra a los partidos donde es el árbitro asignado; Arbitro y User se enlazan por persona_id.
     * Los demás roles con el permiso (Capturista, Comisión Arbitral y otros) acceden a todas, como pide HU-037.
     */
    private function accesoDelArbitro(User $usuario, Partido $partido): Response
    {
        if (! $usuario->hasRole(self::ROL_ARBITRO)) {
            return Response::allow();
        }

        $arbitro = $partido->arbitro()->first(['id', 'persona_id']);

        return $usuario->persona_id !== null && $arbitro !== null && (int) $arbitro->persona_id === (int) $usuario->persona_id
            ? Response::allow()
            : Response::deny('Solo el árbitro asignado a este partido puede acceder a su cédula.');
    }
}
