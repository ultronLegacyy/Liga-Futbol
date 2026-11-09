<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoPersona extends Model
{
    protected $table = 'documentos_persona';

    /** @var list<string> */
    protected $fillable = ['persona_id', 'tipo', 'ruta_storage', 'disco', 'mime_type', 'tamano_bytes'];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }
}
