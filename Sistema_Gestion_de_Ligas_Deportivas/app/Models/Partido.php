<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Partido de una jornada (RF-06.1). HU-025 usa su cancha y su horario, HU-026 su árbitro; HU-014/HU-027 completan el resto
class Partido extends Model
{
    use Auditable;

    // Estados de un partido programado que admiten asignar árbitro (RF-06.3)
    public const ESTADOS_ASIGNABLES = ['programado', 'confirmado'];

    protected $table = 'partidos';

    protected $fillable = [
        'jornada_id',
        'equipo_local_id',
        'equipo_visitante_id',
        'cancha_id',
        'arbitro_id',
        'fecha_partido',
        'hora_partido',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_partido' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Jornada, $this> */
    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    /** @return BelongsTo<Equipo, $this> */
    public function local(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_local_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function visitante(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_visitante_id');
    }

    /** @return BelongsTo<Cancha, $this> */
    public function cancha(): BelongsTo
    {
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

    /** @return BelongsTo<Arbitro, $this> */
    public function arbitro(): BelongsTo
    {
        return $this->belongsTo(Arbitro::class);
    }

    /**
     * Partidos que ocupan su cancha: tienen horario y no están suspendidos (RF-05.3).
     *
     * @param  Builder<Partido>  $query
     * @return Builder<Partido>
     */
    public function scopeQueOcupanCancha(Builder $query): Builder
    {
        return $query->whereNotNull('fecha_partido')
            ->whereNotNull('hora_partido')
            ->where('estado', '!=', 'suspendido');
    }
}
