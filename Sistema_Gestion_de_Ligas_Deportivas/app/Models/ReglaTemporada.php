<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglaTemporada extends Model
{
    protected $table = 'regla_temporadas';

    protected $fillable = [
        'temporada_id',
        'version',
        'reglas',
        'usuario_id',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'reglas' => 'array',
        ];
    }

    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
