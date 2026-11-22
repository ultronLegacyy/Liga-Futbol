<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asistencia de un club a una asamblea: quién fue en su representación (HU-053, RF-14.1).
 *
 * @property int $id
 * @property int $asamblea_id
 * @property int $club_id
 * @property int|null $delegado_usuario_id
 * @property string|null $nombre_asistente
 * @property bool $asistio
 * @property string|null $observaciones
 * @property int $registrado_por_usuario_id
 */
class AsistenciaAsamblea extends Model
{
    use Auditable;

    protected $table = 'asistencias_asamblea';

    protected $fillable = [
        'asamblea_id',
        'club_id',
        'delegado_usuario_id',
        'nombre_asistente',
        'asistio',
        'observaciones',
        'registrado_por_usuario_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['asistio' => 'boolean'];
    }

    /** @return BelongsTo<Asamblea, $this> */
    public function asamblea(): BelongsTo
    {
        return $this->belongsTo(Asamblea::class);
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function delegado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegado_usuario_id');
    }
}
