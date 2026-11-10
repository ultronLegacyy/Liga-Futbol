<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Persona;
use App\Models\Temporada;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Requisitos de edad para inscribir a un jugador: la edad mínima de la Liga y las reglas
 * de edad de la categoría (reglas de la temporada vigente).
 *
 * La comparten el registro (HU-016), la renovación y la transferencia (HU-017), así que
 * ninguna de las tres puede saltarse la edad mínima de la Liga.
 * Fecha de corte de la categoría provisional: el inicio de la temporada destino, pendiente
 * de confirmación del Product Owner.
 */
class ReglaEdadCategoriaService
{
    public function validar(Persona $persona, Categoria $categoria, Temporada $temporada, string $campo = 'equipo_id'): void
    {
        $this->validarEdadMinimaLiga($persona, $campo);

        $nacimiento = Carbon::parse($persona->fecha_nacimiento);
        $anioNacimiento = (int) $nacimiento->year;
        $edad = (int) $nacimiento->diffInYears(Carbon::parse($temporada->fecha_inicio));

        $fueraDeAnios = ($categoria->anio_nacimiento_desde !== null && $anioNacimiento < (int) $categoria->anio_nacimiento_desde)
            || ($categoria->anio_nacimiento_hasta !== null && $anioNacimiento > (int) $categoria->anio_nacimiento_hasta);
        $fueraDeEdad = ($categoria->edad_min !== null && $edad < (int) $categoria->edad_min)
            || ($categoria->edad_max !== null && $edad > (int) $categoria->edad_max);

        if ($fueraDeAnios || $fueraDeEdad) {
            throw ValidationException::withMessages([
                $campo => 'El jugador no cumple los requisitos de edad de la categoría '.$categoria->nombre.'.',
            ]);
        }
    }

    /** Edad mínima de la Liga (config/jugadores.php), o null si la regla está desactivada. */
    public function edadMinimaLiga(): ?int
    {
        $edad = config('jugadores.edad_minima');

        return $edad === null ? null : (int) $edad;
    }

    /**
     * Regla de la Liga: solo se inscriben jugadores con la edad mínima cumplida a la fecha
     * de la operación, sin importar la categoría.
     */
    public function validarEdadMinimaLiga(Persona $persona, string $campo = 'equipo_id', ?CarbonInterface $fecha = null): void
    {
        $minima = $this->edadMinimaLiga();

        if ($minima === null) {
            return;
        }

        $edad = (int) Carbon::parse($persona->fecha_nacimiento)->diffInYears($fecha ?? now());

        if ($edad < $minima) {
            throw ValidationException::withMessages([
                $campo => "La Liga solo inscribe jugadores mayores de edad: el jugador debe tener {$minima} años cumplidos.",
            ]);
        }
    }
}
