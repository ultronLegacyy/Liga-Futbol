<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Support\Curp;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Requisitos de edad de una categoría (reglas de la temporada vigente).
 *
 * La comparten el registro (HU-016), la renovación y la transferencia (HU-017), y en la
 * rama infantil también la alineación de la cédula, contra la CURP (HU-021).
 * Fecha de corte: el inicio de la temporada destino (respuesta 14 del Product Owner
 * en la revisión del #73; por ejemplo, «18 años o más» en la rama Libre).
 */
class ReglaEdadCategoriaService
{
    public function validar(Persona $persona, Categoria $categoria, Temporada $temporada, string $campo = 'equipo_id'): void
    {
        if (! $this->cumple(Carbon::parse($persona->fecha_nacimiento), $categoria, $temporada)) {
            throw ValidationException::withMessages([
                $campo => 'El jugador no cumple los requisitos de edad de la categoría '.$categoria->nombre.'.',
            ]);
        }

        $this->validarCurpInfantil($persona, $categoria, $temporada, $campo);
    }

    /**
     * HU-021 (RF-04.1, RF-04.4): en la rama infantil la edad también se comprueba con la fecha de
     * nacimiento que indica la CURP, para que una fecha mal capturada no cuele a un jugador en otra categoría.
     */
    public function validarCurpInfantil(Persona $persona, Categoria $categoria, Temporada $temporada, string $campo = 'equipo_id'): void
    {
        if ($categoria->rama !== 'Infantil' || ! $this->tieneRequisitos($categoria)) {
            return;
        }

        $nacimiento = Curp::fechaNacimiento($persona->curp);

        if ($nacimiento === null) {
            throw ValidationException::withMessages([
                $campo => 'La CURP del jugador no tiene una fecha de nacimiento válida; corrígela antes de inscribirlo en la rama infantil.',
            ]);
        }

        if (! $this->cumple($nacimiento, $categoria, $temporada)) {
            throw ValidationException::withMessages([
                $campo => 'Según su CURP, el jugador nació el '.$nacimiento->format('d/m/Y').': no cumple el criterio de edad de la categoría '
                    .$categoria->nombre.' ('.$this->criterio($categoria).').',
            ]);
        }
    }

    /**
     * HU-021: al marcar la asistencia de un jugador de la rama infantil en la cédula, se vuelve a
     * comprobar su CURP contra la categoría del equipo con el que juega.
     */
    public function validarAlineacion(Jugador $jugador, string $campo = 'asistio'): void
    {
        $jugador->loadMissing(['persona', 'equipo.grupo.categoria.temporada', 'equipo.temporada']);
        $equipo = $jugador->equipo;
        $categoria = $equipo->grupo?->categoria;
        // La temporada del equipo o, si el equipo no la tiene, la de su categoría
        $temporada = $equipo->temporada ?? $categoria?->temporada;

        if ($categoria === null || $temporada === null || $jugador->persona === null) {
            return;
        }

        $this->validarCurpInfantil($jugador->persona, $categoria, $temporada, $campo);
    }

    private function cumple(CarbonInterface $nacimiento, Categoria $categoria, Temporada $temporada): bool
    {
        $anioNacimiento = (int) $nacimiento->year;
        $edad = (int) $nacimiento->diffInYears(Carbon::parse($temporada->fecha_inicio));

        $fueraDeAnios = ($categoria->anio_nacimiento_desde !== null && $anioNacimiento < (int) $categoria->anio_nacimiento_desde)
            || ($categoria->anio_nacimiento_hasta !== null && $anioNacimiento > (int) $categoria->anio_nacimiento_hasta);
        $fueraDeEdad = ($categoria->edad_min !== null && $edad < (int) $categoria->edad_min)
            || ($categoria->edad_max !== null && $edad > (int) $categoria->edad_max);

        return ! $fueraDeAnios && ! $fueraDeEdad;
    }

    private function tieneRequisitos(Categoria $categoria): bool
    {
        return $categoria->anio_nacimiento_desde !== null || $categoria->anio_nacimiento_hasta !== null
            || $categoria->edad_min !== null || $categoria->edad_max !== null;
    }

    // Criterio de la categoría en palabras, para explicar el bloqueo (Tabla 4 de la ERS)
    private function criterio(Categoria $categoria): string
    {
        $desde = $categoria->anio_nacimiento_desde;
        $hasta = $categoria->anio_nacimiento_hasta;
        $partes = [];

        if ($desde !== null && $hasta !== null) {
            $partes[] = $desde === $hasta ? 'nacidos en '.$desde : 'nacidos de '.$desde.' a '.$hasta;
        } elseif ($desde !== null) {
            $partes[] = 'nacidos en '.$desde.' o después';
        } elseif ($hasta !== null) {
            $partes[] = 'nacidos en '.$hasta.' o antes';
        }

        if ($categoria->edad_min !== null && $categoria->edad_max !== null) {
            $partes[] = 'de '.$categoria->edad_min.' a '.$categoria->edad_max.' años al inicio de la temporada';
        } elseif ($categoria->edad_min !== null) {
            $partes[] = $categoria->edad_min.' años o más al inicio de la temporada';
        } elseif ($categoria->edad_max !== null) {
            $partes[] = 'hasta '.$categoria->edad_max.' años al inicio de la temporada';
        }

        return implode('; ', $partes);
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
