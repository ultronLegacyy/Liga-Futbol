<?php

namespace App\Policies;

use App\Models\FaseTorneo;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * HU-055 y HU-056: generar las llaves de liguilla y crear sus rondas. Lo hace quien puede modificar la temporada
 * (Presidente y Administrador General, D-043 y decisión 4 de Daniel), nunca en una temporada cerrada; el mensaje habla
 * de la liguilla, no de temporadas. HU-060: lo mismo para la liguilla de consolación y el Campeón de Campeones.
 */
class LlaveLiguillaPolicy
{
    // Por fase: [lo que ya no se hace con la temporada cerrada, mensaje para quien no tiene el rol]
    private const GENERAR = [
        FaseTorneo::LIGUILLA => ['ya no se generan llaves de liguilla', 'Solo el Presidente o el Administrador General pueden generar las llaves de liguilla.'],
        FaseTorneo::CONSOLACION => ['ya no se generan llaves de liguilla de consolación', 'Solo el Presidente o el Administrador General pueden generar las llaves de la liguilla de consolación.'],
        FaseTorneo::CAMPEON_DE_CAMPEONES => ['ya no se registra el Campeón de Campeones', 'Solo el Presidente o el Administrador General pueden registrar el Campeón de Campeones.'],
    ];

    private const CREAR_RONDA = [
        FaseTorneo::LIGUILLA => ['ya no se crean rondas de liguilla', 'Solo el Presidente o el Administrador General pueden crear las rondas de la liguilla.'],
        FaseTorneo::CONSOLACION => ['ya no se crean rondas de liguilla de consolación', 'Solo el Presidente o el Administrador General pueden crear las rondas de la liguilla de consolación.'],
        FaseTorneo::CAMPEON_DE_CAMPEONES => ['ya no se crea el partido de Campeón de Campeones', 'Solo el Presidente o el Administrador General pueden crear el partido de Campeón de Campeones.'],
    ];

    public function create(User $usuario, Grupo $grupo, string $tipo = FaseTorneo::LIGUILLA): Response
    {
        return $this->operar($usuario, $grupo, ...self::GENERAR[$tipo]);
    }

    public function crearRonda(User $usuario, Grupo $grupo, string $tipo = FaseTorneo::LIGUILLA): Response
    {
        return $this->operar($usuario, $grupo, ...self::CREAR_RONDA[$tipo]);
    }

    private function operar(User $usuario, Grupo $grupo, string $cerrada, string $mensaje): Response
    {
        $temporada = $grupo->temporada;

        if ($temporada === null || $temporada->estado === 'cerrada') {
            return Response::deny("La temporada de este grupo está cerrada: {$cerrada}.");
        }

        return $usuario->can('update', $temporada) ? Response::allow() : Response::deny($mensaje);
    }
}
