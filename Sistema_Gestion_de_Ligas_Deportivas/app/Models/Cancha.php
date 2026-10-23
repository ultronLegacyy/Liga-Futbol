<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\CanchaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

// Cancha o sede del catalogo que usa la programacion de partidos (RF-05.1, RF-05.2)
class Cancha extends Model
{
    /** @use HasFactory<CanchaFactory> */
    use HasFactory;
    use Auditable;

    protected $table = 'canchas';

    // El estado no se captura directamente en el catalogo general; los bloqueos por mantenimiento o lluvia son de HU-024
    protected $fillable = ['nombre', 'direccion_ubicacion', 'capacidad_partidos_consecutivos', 'estado'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacidad_partidos_consecutivos' => 'integer',
        ];
    }

    /**
     * Bloqueos temporales registrados sobre la cancha (HU-024, RF-05.2).
     *
     * @return HasMany<BloqueoCancha, $this>
     */
    public function bloqueos(): HasMany
    {
        return $this->hasMany(BloqueoCancha::class, 'cancha_id');
    }

    /**
     * Determina si la cancha se encuentra disponible para juego en un periodo especifico (HU-024).
     *
     * Permite firmas flexibles:
     * - ($fecha, $horaInicio, $horaFin) ej: ('2026-11-01', '10:00:00', '12:00:00')
     * - ($inicioDateTime, $finDateTime) ej: ('2026-11-01 10:00:00', '2026-11-01 12:00:00')
     * - ($momento) evalua disponibilidad en un instante especifico
     */
    public function estaDisponible(
        CarbonInterface|string $fechaOInicio,
        CarbonInterface|string|null $horaOFina = null,
        ?string $horaFin = null
    ): bool {
        // Canchas inactivas quedan descartadas de la programacion deportiva
        if ($this->estado === 'Inactiva') {
            return false;
        }

        // Caso A: firma con 3 argumentos ($fecha, $horaInicio, $horaFin)
        if ($horaFin !== null && is_string($horaOFina)) {
            $inicio = Carbon::parse("{$fechaOInicio} {$horaOFina}");
            $fin = Carbon::parse("{$fechaOInicio} {$horaFin}");
        } elseif ($horaOFina !== null) {
            // Caso B: firma con 2 argumentos ($inicioDateTime, $finDateTime)
            $inicio = $fechaOInicio instanceof CarbonInterface ? $fechaOInicio : Carbon::parse($fechaOInicio);
            $fin = $horaOFina instanceof CarbonInterface ? $horaOFina : Carbon::parse($horaOFina);
        } else {
            // Caso C: un solo argumento, instante puntual
            $momento = $fechaOInicio instanceof CarbonInterface ? $fechaOInicio : Carbon::parse($fechaOInicio);

            return ! $this->bloqueos()->vigentes($momento)->exists();
        }

        if ($fin->lessThanOrEqualTo($inicio)) {
            throw new InvalidArgumentException('La fecha y hora de termino debe ser posterior al inicio.');
        }

        // Si existe algun bloqueo que se solape con el intervalo, la cancha NO esta disponible
        return ! $this->bloqueos()->solapadosCon($inicio, $fin)->exists();
    }

    /**
     * Scope para filtrar canchas operativas y sin bloqueos en un rango de fechas y horas dado.
     * Consumido por los algoritmos de calendarizacion (HU-025 y HU-027).
     *
     * @param  Builder<Cancha>  $query
     * @return Builder<Cancha>
     */
    public function scopeDisponiblesEn(Builder $query, CarbonInterface|string $inicio, CarbonInterface|string $fin): Builder
    {
        $inicioCarbon = $inicio instanceof CarbonInterface ? $inicio : Carbon::parse($inicio);
        $finCarbon = $fin instanceof CarbonInterface ? $fin : Carbon::parse($fin);

        return $query->where('estado', '!=', 'Inactiva')
            ->whereDoesntHave('bloqueos', function (Builder $q) use ($inicioCarbon, $finCarbon) {
                $q->where('fecha_inicio', '<', $finCarbon)
                    ->where('fecha_fin', '>', $inicioCarbon);
            });
    }
}
