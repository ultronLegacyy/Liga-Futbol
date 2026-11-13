<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Copia histórica de la tabla de un grupo al cierre de una jornada (HU-043 CA2, RF-09.3).
 * Se guarda una sola vez, cuando la jornada y todas las anteriores tienen sus resultados oficiales.
 *
 * @property int $id
 * @property int $grupo_id
 * @property int $jornada_id
 * @property int $equipo_id
 * @property int|null $posicion
 * @property int $pj
 * @property int $pg
 * @property int $pe
 * @property int $pp
 * @property int $gf
 * @property int $gc
 * @property int $dg
 * @property int $puntos
 * @property int|null $regla_version
 */
class TablaPosicionJornada extends Model
{
    protected $table = 'tablas_posiciones_jornada';

    protected $fillable = [
        'grupo_id',
        'jornada_id',
        'equipo_id',
        'posicion',
        'pj',
        'pg',
        'pe',
        'pp',
        'gf',
        'gc',
        'dg',
        'puntos',
        'regla_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'pj' => 'integer',
            'pg' => 'integer',
            'pe' => 'integer',
            'pp' => 'integer',
            'gf' => 'integer',
            'gc' => 'integer',
            'dg' => 'integer',
            'puntos' => 'integer',
            'regla_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** @return BelongsTo<Jornada, $this> */
    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
