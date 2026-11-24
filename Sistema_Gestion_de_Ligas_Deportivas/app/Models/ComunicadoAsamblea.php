<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property bool $publicado_en_portal
 * @property int|null $publicado_por_usuario_id
 * @property \Illuminate\Support\Carbon|null $publicado_en
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ComunicadoAsamblea extends Model
{
    use Auditable;

    protected $table = 'comunicados_asamblea';

    // La publicación en el portal no se asigna en masa: solo la cambia AsambleaService::publicarComunicadoEnPortal()
    protected $fillable = ['asamblea_id', 'titulo', 'contenido', 'registrado_por_usuario_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publicado_en_portal' => 'boolean',
            'publicado_en' => 'datetime',
        ];
    }

    /**
     * Comunicados visibles en el portal público (HU-078, RF-12.3), del más reciente al más antiguo.
     *
     * @param  Builder<ComunicadoAsamblea>  $query
     * @return Builder<ComunicadoAsamblea>
     */
    public function scopePublicadosEnPortal(Builder $query): Builder
    {
        return $query->where('publicado_en_portal', true)
            ->whereNotNull('publicado_en')
            ->orderByDesc('publicado_en')
            ->orderByDesc('id');
    }

    /** @return BelongsTo<Asamblea, $this> */
    public function asamblea(): BelongsTo
    {
        return $this->belongsTo(Asamblea::class);
    }

    /** @return BelongsTo<User, $this> */
    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por_usuario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_usuario_id');
    }
}
