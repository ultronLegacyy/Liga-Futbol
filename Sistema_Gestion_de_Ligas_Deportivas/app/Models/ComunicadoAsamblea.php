<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comunicado emitido por una asamblea (HU-054, RF-14.2).
 *
 * @property int $id
 * @property int $asamblea_id
 * @property string $titulo
 * @property string $contenido
 * @property int $registrado_por_usuario_id
 */
class ComunicadoAsamblea extends Model
{
    use Auditable;

    protected $table = 'comunicados_asamblea';

    protected $fillable = ['asamblea_id', 'titulo', 'contenido', 'registrado_por_usuario_id'];

    /** @return BelongsTo<Asamblea, $this> */
    public function asamblea(): BelongsTo
    {
        return $this->belongsTo(Asamblea::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_usuario_id');
    }
}
