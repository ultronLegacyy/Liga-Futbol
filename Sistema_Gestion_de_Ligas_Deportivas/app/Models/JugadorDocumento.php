<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JugadorDocumento extends Model
{
    use Auditable;

    protected $table = 'jugador_documentos';

    /** @var list<string> */
    protected $fillable = ['jugador_id', 'tipo', 'disco', 'ruta_storage', 'mime_type', 'tamano_bytes'];

    /** @return BelongsTo<Jugador, $this> */
    public function jugador(): BelongsTo
    {
        return $this->belongsTo(Jugador::class, 'jugador_id');
    }
}
