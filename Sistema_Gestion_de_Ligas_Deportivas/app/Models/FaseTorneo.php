<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fase de la temporada: Regular, Liguilla, Consolación o Campeón de Campeones (RF-11). HU-055 crea la de Liguilla.
 *
 * @property int $id
 * @property int $temporada_id
 * @property string $tipo
 */
class FaseTorneo extends Model
{
    use Auditable;

    public const LIGUILLA = 'Liguilla';

    protected $table = 'fases_torneo';

    protected $fillable = ['temporada_id', 'tipo'];

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /** @return HasMany<LlaveLiguilla, $this> */
    public function llaves(): HasMany
    {
        return $this->hasMany(LlaveLiguilla::class, 'fase_id');
    }
}
