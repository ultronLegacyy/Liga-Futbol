<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Infracción o artículo tipificado en el Reglamento de Sanciones del Sector Amateur FMF (REF-05 / RF-10.1).
 *
 * @property int $id
 * @property string $codigo
 * @property string $descripcion
 * @property int $castigo_base_partidos
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CatalogoArticulo extends Model
{
    use HasFactory;

    protected $table = 'catalogo_articulos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'castigo_base_partidos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'castigo_base_partidos' => 'integer',
        ];
    }

    /**
     * Expedientes donde este artículo fue el artículo principal asignado.
     *
     * @return HasMany<ExpedienteDisciplinario, $this>
     */
    public function expedientes(): HasMany
    {
        return $this->hasMany(ExpedienteDisciplinario::class, 'articulo_id');
    }

    /**
     * Expedientes donde este artículo figura como presuntamente infringido (muchos a muchos).
     *
     * @return BelongsToMany<ExpedienteDisciplinario, $this>
     */
    public function expedientesPresuntos(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpedienteDisciplinario::class,
            'expediente_articulos',
            'articulo_id',
            'expediente_id'
        )->withPivot('observaciones')->withTimestamps();
    }
}
