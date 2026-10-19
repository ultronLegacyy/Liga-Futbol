<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Convocatoria extends Model
{
    use HasFactory;
    use Auditable;
    protected $table = 'convocatorias';
    protected $fillable = ['temporada_id', 'archivo_pdf', 'reglas_versionadas'];

    // Temporada a la que pertenece la convocatoria
    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }
}
