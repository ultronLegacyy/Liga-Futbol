<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Identidad única y reutilizable de las personas (HU-013, RF-03.2). La autorización le toca a quien lo llame (HU-016, HU-022, HU-026)
class IdentidadPersonaService
{
    /**
     * Asigna un rol en una temporada a la persona con esa CURP. Si la persona ya existe se reutiliza
     * su identidad sin modificar sus datos (CA1); si ya tenía ese rol en la temporada, se devuelve el mismo.
     *
     * @param array{nombre: string, apellidos: string, fecha_nacimiento: string, curp: string} $datos
     */
    public function asignarRol(array $datos, string $rol, Temporada $temporada): PersonaRol
    {
        if (! in_array($rol, PersonaRol::ROLES, true)) {
            throw ValidationException::withMessages([
                'rol' => 'El rol debe ser jugador, delegado, árbitro o directivo.',
            ]);
        }

        $curp = $this->normalizarCurp($datos['curp']);

        if ($curp === '') {
            throw ValidationException::withMessages([
                'curp' => 'La CURP es obligatoria para identificar a la persona.',
            ]);
        }

        return DB::transaction(function () use ($datos, $rol, $temporada, $curp): PersonaRol {
            // Lectura compartida: la temporada no se cierra mientras se registra el rol
            $vigente = Temporada::query()
                ->whereKey($temporada->getKey())
                ->sharedLock()
                ->firstOrFail();

            if ($vigente->estado === 'cerrada') {
                throw ValidationException::withMessages([
                    'temporada_id' => 'Una temporada cerrada no admite cambios en los roles de sus personas.',
                ]);
            }

            // createOrFirst resuelve dos altas simultáneas de la misma CURP con el índice único de la base
            $persona = Persona::query()->createOrFirst(
                ['curp' => $curp],
                Arr::only($datos, ['nombre', 'apellidos', 'fecha_nacimiento'])
            );

            // La persona va cargada en el rol: wasRecentlyCreated indica si se creó o se reutilizó
            return PersonaRol::query()->createOrFirst([
                'persona_id' => $persona->id,
                'rol' => $rol,
                'temporada_id' => $vigente->id,
            ])->setRelation('persona', $persona);
        });
    }

    /**
     * Busca la identidad por CURP sin distinguir mayúsculas ni espacios.
     */
    public function buscarPorCurp(string $curp): ?Persona
    {
        return Persona::query()->where('curp', $this->normalizarCurp($curp))->first();
    }

    private function normalizarCurp(string $curp): string
    {
        return mb_strtoupper(trim($curp));
    }
}
