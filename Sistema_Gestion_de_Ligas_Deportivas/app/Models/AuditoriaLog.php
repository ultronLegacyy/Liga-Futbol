<?php

namespace App\Models;

use App\Exceptions\AuditoriaInmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaLog extends Model
{
    // La auditoría solo se inserta: no tiene fecha de actualización
    public const UPDATED_AT = null;

    protected $table = 'auditoria_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'accion',
        'tabla_afectada',
        'registro_id',
        'valores_anteriores',
        'valores_nuevos',
        'motivo',
        'ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Inmutable a nivel aplicación; los triggers de la base cubren las consultas directas
        static::updating(function (): never {
            throw new AuditoriaInmutableException();
        });

        static::deleting(function (): never {
            throw new AuditoriaInmutableException();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
