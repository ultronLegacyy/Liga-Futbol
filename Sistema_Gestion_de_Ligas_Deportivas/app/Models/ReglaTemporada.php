<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReglaTemporada extends Model
{
    use Auditable;

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

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException(
                'Las versiones de reglas son inmutables y no pueden modificarse.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Las versiones de reglas son inmutables y no pueden eliminarse.'
            );
        });
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
