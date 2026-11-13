<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Incidencia del partido en texto libre, con minuto y equipo opcionales (HU-036, decisión de Daniel del 11/11).
 *
 * @property int $id
 * @property int $cedula_id
 * @property int|null $equipo_id
 * @property int|null $minuto
 * @property string $descripcion
 * @property string $clave_envio
 */
class IncidenciaPartido extends Model
{
    use Auditable;

    protected $table = 'incidencias_partido';

    protected $fillable = [
        'cedula_id',
        'equipo_id',
        'minuto',
        'descripcion',
        'clave_envio',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minuto' => 'integer',
        ];
    }

    /** @return BelongsTo<CedulaArbitral, $this> */
    public function cedula(): BelongsTo
    {
        return $this->belongsTo(CedulaArbitral::class, 'cedula_id');
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }
}
