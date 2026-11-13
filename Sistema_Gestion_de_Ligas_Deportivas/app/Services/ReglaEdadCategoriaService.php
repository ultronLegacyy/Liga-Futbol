<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Persona;
use App\Models\Temporada;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Requisitos de edad de una categoría (reglas de la temporada vigente).
 *
 * La comparten el registro (HU-016), la renovación y la transferencia (HU-017).
 * Fecha de corte provisional: el inicio de la temporada destino, pendiente de
 * confirmación del Product Owner.
 */
class ReglaEdadCategoriaService
{
    public function validar(Persona $persona, Categoria $categoria, Temporada $temporada, string $campo = 'equipo_id'): void
    {
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

    /**
     * Años cumplidos a una fecha; sin fecha, a «hoy» con la hora de México. Compara solo
     * fechas: la zona de la app es UTC y desde las 18:00 de México ya va en el día siguiente.
     */
    public static function edadCumplida(Persona $persona, ?CarbonInterface $fecha = null): int
    {
        $hoy = Carbon::parse(($fecha ?? now(GestionJugadorService::ZONA_HORARIA))->toDateString());
        $nacimiento = Carbon::parse(Carbon::parse($persona->fecha_nacimiento)->toDateString());

        return (int) $nacimiento->diffInYears($hoy);
    }
}
