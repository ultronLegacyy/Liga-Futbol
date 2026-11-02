<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectivoClub extends Model
{
    use HasFactory;

    protected $table = 'directivos_club';

    protected $fillable = [
        'club_id',
        'nombre',
        'cargo',
        'firma_digital_url',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
