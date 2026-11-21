<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Modelo pivote auditable para la imputación de artículos reglamentarios en expedientes disciplinarios (HU-044 / RNF-30).
 *
 * @property int $id
 * @property int $expediente_id
 * @property int $articulo_id
 * @property string|null $observaciones
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ExpedienteArticulo extends Pivot
{
    use Auditable;

    /**
     * @var bool
     */
    public $incrementing = true;

    /**
     * @var string
     */
    protected $table = 'expediente_articulos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'expediente_id',
        'articulo_id',
        'observaciones',
    ];
}
