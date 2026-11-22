<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comunicado por correo a un grupo de usuarios (HU-066, RF-15.3).
 *
 * @property int $id
 * @property int|null $enviado_por_usuario_id
 * @property string $asunto
 * @property string $mensaje
 * @property array{roles: list<string>, categoria_id: int|null, clubes: list<int>} $filtros
 * @property int $total_destinatarios
 */
class Comunicado extends Model
{
    use Auditable;

    protected $table = 'comunicados';

    protected $fillable = ['enviado_por_usuario_id', 'asunto', 'mensaje', 'filtros', 'total_destinatarios'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['filtros' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_usuario_id');
    }
}
