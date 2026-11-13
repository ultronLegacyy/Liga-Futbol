<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cruce de liguilla generado desde la tabla final de un grupo (HU-055, RF-11.1).
 * Guarda el lugar de cada equipo y las reglas con que se generó (RNF-36).
 *
 * @property int $id
 * @property int $fase_id
 * @property int|null $grupo_id
 * @property int|null $orden
 * @property int $equipo_local_id
 * @property int|null $lugar_local
 * @property int $equipo_visitante_id
 * @property int|null $lugar_visitante
 * @property int|null $regla_temporada_id
 * @property int|null $regla_version
 * @property array<string, int>|null $criterios_clasificacion
 * @property int|null $generada_por_id
 */
class LlaveLiguilla extends Model
{
    use Auditable;

    protected $table = 'llaves_liguilla';

    protected $fillable = [
        'fase_id',
        'grupo_id',
        'orden',
        'equipo_local_id',
        'lugar_local',
        'equipo_visitante_id',
        'lugar_visitante',
        'regla_temporada_id',
        'regla_version',
        'criterios_clasificacion',
        'generada_por_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'lugar_local' => 'integer',
            'lugar_visitante' => 'integer',
            'regla_version' => 'integer',
            'criterios_clasificacion' => 'array',
        ];
    }

    /** @return BelongsTo<FaseTorneo, $this> */
    public function fase(): BelongsTo
    {
        return $this->belongsTo(FaseTorneo::class, 'fase_id');
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
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

    /** @return BelongsTo<ReglaTemporada, $this> */
    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaTemporada::class, 'regla_temporada_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generada_por_id');
    }
}
