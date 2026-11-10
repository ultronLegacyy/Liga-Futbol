<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Persona;
use App\Models\Temporada;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReglaTransferenciaJugadorService
{
    public function validarTransferencia(Categoria $origen, Categoria $destino, Persona $persona, Temporada $temporada): void
    {
        $this->validarCategorias($origen, $destino);
        $nacimiento = $persona->fecha_nacimiento;
        $edadEnInicioTemporada = $nacimiento->diffInYears(Carbon::parse($temporada->fecha_inicio));
        $anioNacimiento = $nacimiento->year;

        if (($destino->anio_nacimiento_desde !== null && $anioNacimiento < $destino->anio_nacimiento_desde)
            || ($destino->anio_nacimiento_hasta !== null && $anioNacimiento > $destino->anio_nacimiento_hasta)
            || ($destino->edad_min !== null && $edadEnInicioTemporada < $destino->edad_min)) {
            throw ValidationException::withMessages(['equipo_id' => 'El jugador no cumple los requisitos de edad de la categoría destino.']);
        }
    }

    public function validarCategorias(Categoria $origen, Categoria $destino): void
    {
        // Igual que HU-012 (criterio 2) y la renovación: solo se bloquea subir más de 1 nivel.
        // La dirección permitida está pendiente de confirmación del Product Owner.
        if ((int) $destino->jerarquia_salto > (int) $origen->jerarquia_salto + 1) {
            throw ValidationException::withMessages(['categoria_id' => 'La transferencia solo permite avanzar una categoría respecto a la categoría de origen.']);
        }
    }
}
