<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Registro y auditoría de reprogramaciones y cambios de estado de partidos (HU-029 / RF-06.4 / RNF-22)
class ReprogramacionPartido extends Model
{
    use HasFactory;
    use Auditable;

    protected $table = 'reprogramaciones_partidos';

    protected $fillable = [
        'partido_id',
        'usuario_id',
        'estado_anterior',
        'estado_nuevo',
        'cancha_anterior_id',
        'cancha_nueva_id',
        'fecha_anterior',
        'fecha_nueva',
        'hora_anterior',
        'hora_nueva',
        'motivo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_anterior' => 'date:Y-m-d',
            'fecha_nueva' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return BelongsTo<Cancha, $this>
     */
    public function canchaAnterior(): BelongsTo
    {
        return $this->belongsTo(Cancha::class, 'cancha_anterior_id');
    }

    /**
     * @return BelongsTo<Cancha, $this>
     */
    public function canchaNueva(): BelongsTo
    {
        return $this->belongsTo(Cancha::class, 'cancha_nueva_id');
    }
}
