<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Cédula arbitral oficial del partido (RF-08.1, RF-08.4; HU-031, RF-06.6)
class CedulaArbitral extends Model
{
    use HasFactory;
    use Auditable;

    protected $table = 'cedulas_arbitrales';

    protected $fillable = [
        'partido_id',
        'goles_local',
        'goles_visitante',
        'informe_arbitral',
        'firma_capitan_local',
        'firma_capitan_visitante',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'goles_local' => 'integer',
            'goles_visitante' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Partido, $this>
     */
    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }
}
