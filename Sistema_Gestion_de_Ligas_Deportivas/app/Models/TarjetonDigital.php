<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property array<string, mixed> $contenido */
class TarjetonDigital extends Model
{
    use HasFactory;

    protected $table = 'tarjetones_digitales';

    protected $fillable = [
        'club_id',
        'temporada_id',
        'version',
        'contenido',
        'generado_en',
        'generado_por_usuario_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'contenido' => 'array',
            'generado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<Temporada, $this> */
    public function temporada(): BelongsTo
    {
        return $this->belongsTo(Temporada::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por_usuario_id');
    }
}
