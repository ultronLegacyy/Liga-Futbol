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

    public const CONSOLACION = 'Consolacion';

    public const CAMPEON_DE_CAMPEONES = 'CampeonDeCampeones';

    // HU-060: nombre de cada fase final en pantalla y en los mensajes
    public const NOMBRES = [
        self::LIGUILLA => 'Liguilla',
        self::CONSOLACION => 'Liguilla de consolación',
        self::CAMPEON_DE_CAMPEONES => 'Campeón de Campeones',
    ];

    // La misma fase dentro de una frase: «las llaves de liguilla de consolación»
    public const EN_TEXTO = [
        self::LIGUILLA => 'liguilla',
        self::CONSOLACION => 'liguilla de consolación',
        self::CAMPEON_DE_CAMPEONES => 'Campeón de Campeones',
    ];

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
