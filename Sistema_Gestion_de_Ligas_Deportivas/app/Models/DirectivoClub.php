<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectivoClub extends Model
{
    use HasFactory;

    protected $table = 'directivos_club';

    /** @var list<string> */
    protected $fillable = [
        'club_id',
        'nombre',
        'cargo',
        'firma_digital_url',
    ];

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
