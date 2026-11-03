<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Partido de una jornada. HU-025 solo necesita su cancha y su horario; HU-027 completa el resto
class Partido extends Model
{
    use Auditable;

    protected $table = 'partidos';

    protected $fillable = [
        'jornada_id',
        'equipo_local_id',
        'equipo_visitante_id',
        'cancha_id',
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
            'fecha_partido' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Cancha, $this>
     */
    public function cancha(): BelongsTo
    {
        return $this->belongsTo(Cancha::class, 'cancha_id');
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
