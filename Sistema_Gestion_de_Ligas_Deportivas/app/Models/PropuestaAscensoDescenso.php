<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de una propuesta de ascenso o descenso de un grupo (HU-057, RF-11.3). Las versiones no se modifican:
 * una propuesta nueva es otra versión, y cada una conserva las reglas con que se generó (RNF-36).
 *
 * @property int $id
 * @property int $grupo_id
 * @property int $version
 * @property int $equipo_id
 * @property string $movimiento
 * @property int $posicion
 * @property int $puntos
 * @property int $regla_temporada_id
 * @property int $regla_version
 * @property array<string, int> $reglas_ascenso_descenso
 * @property int|null $generada_por_id
 */
class PropuestaAscensoDescenso extends Model
{
    use Auditable;

    public const ASCIENDE = 'asciende';

    public const DESCIENDE = 'desciende';

    protected $table = 'propuestas_ascenso_descenso';

    protected $fillable = [
        'grupo_id',
        'version',
        'equipo_id',
        'movimiento',
        'posicion',
        'puntos',
        'regla_temporada_id',
        'regla_version',
        'reglas_ascenso_descenso',
        'generada_por_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'posicion' => 'integer',
            'puntos' => 'integer',
            'regla_version' => 'integer',
            'reglas_ascenso_descenso' => 'array',
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

    /** @return BelongsTo<User, $this> */
    public function generadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generada_por_id');
    }
}
