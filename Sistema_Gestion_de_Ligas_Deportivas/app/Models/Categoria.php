<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Categoría de una temporada (RF-02.1): división por rama y por año de nacimiento o edad
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;
    use Auditable;

    // Ramas del glosario de la ERS
    public const RAMAS = ['Libre', 'Veteranos', 'Femenil', 'Infantil'];

    protected $table = 'categorias';

    // jerarquia_salto queda con su valor por defecto hasta que la definan HU-019 y RF-11
    protected $fillable = [
        'temporada_id',
        'nombre',
        'rama',
        'anio_nacimiento_desde',
        'anio_nacimiento_hasta',
        'edad_min',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio_nacimiento_desde' => 'integer',
            'anio_nacimiento_hasta' => 'integer',
            'edad_min' => 'integer',
        ];
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    // Grupos en los que se divide la categoría (RF-02.2)
    /** @return HasMany<Grupo, $this> */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }
}
