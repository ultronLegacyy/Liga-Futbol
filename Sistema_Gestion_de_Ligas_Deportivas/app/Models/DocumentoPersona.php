<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoPersona extends Model
{
    protected $table = 'documentos_persona';

    /** @var list<string> */
    protected $fillable = ['persona_id', 'tipo', 'ruta_storage', 'disco', 'mime_type', 'tamano_bytes'];

    // HU-022 CA1 (RNF-30): la ruta interna del archivo no sale en respuestas JSON ni en la bitácora;
    // el documento solo se entrega por jugadores.documentos.show, que revisa la autorización
    protected $hidden = ['ruta_storage', 'disco'];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }
}
