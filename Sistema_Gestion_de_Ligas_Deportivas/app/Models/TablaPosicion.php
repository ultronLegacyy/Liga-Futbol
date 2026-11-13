<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de la tabla de posiciones de un grupo (HU-041, RF-09.1). Es un indicador calculado:
 * se rehace completo a partir de los resultados oficiales (HU-040) cada vez que se valida uno.
 *
 * @property int $id
 * @property int $grupo_id
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
class TablaPosicion extends Model
{
    protected $table = 'tablas_posiciones';

    protected $fillable = [
        'grupo_id',
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

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
