<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\BloqueoCanchaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de bloqueo temporal de una cancha por mantenimiento, clima o causas operativas (HU-024, RF-05.2).
 *
 * @property int $id
 * @property int $cancha_id
 * @property \Illuminate\Support\Carbon|null $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_fin
 * @property string $motivo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Cancha $cancha
 */
class BloqueoCancha extends Model
{
    /** @use HasFactory<BloqueoCanchaFactory> */
    use HasFactory;
    use Auditable;

    protected $table = 'bloqueos_cancha';

    protected $fillable = [
        'cancha_id',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    /**
     * Cancha a la que pertenece el bloqueo temporal.
     *
     * @return BelongsTo<Cancha, $this>
     */
    public function cancha(): BelongsTo
    {
        return $this->belongsTo(Cancha::class, 'cancha_id');
    }

    /**
     * Scope para encontrar bloqueos que se traslapan con un intervalo [inicio, fin].
     *
     * @param  Builder<BloqueoCancha>  $query
     * @return Builder<BloqueoCancha>
     */
    public function scopeSolapadosCon(Builder $query, CarbonInterface|string $inicio, CarbonInterface|string $fin): Builder
    {
        return $query->where('fecha_inicio', '<', $fin)
            ->where('fecha_fin', '>', $inicio);
    }

    /**
     * Scope para consultar bloqueos que estan activos en un momento determinado.
     *
     * @param  Builder<BloqueoCancha>  $query
     * @return Builder<BloqueoCancha>
     */
    public function scopeVigentes(Builder $query, CarbonInterface|string|null $momento = null): Builder
    {
        $momento = $momento ?? now();

        return $query->where('fecha_inicio', '<=', $momento)
            ->where('fecha_fin', '>=', $momento);
    }
}
