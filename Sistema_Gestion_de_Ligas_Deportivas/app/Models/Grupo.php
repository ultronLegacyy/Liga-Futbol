<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\GrupoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Subdivisión de una categoría dentro de su temporada (RF-02.2)
class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use HasFactory;
    use Auditable;

    protected $table = 'grupos';

    protected $fillable = ['categoria_id', 'temporada_id', 'nombre'];

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }
}
