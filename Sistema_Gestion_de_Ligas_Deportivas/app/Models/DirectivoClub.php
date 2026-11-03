<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectivoClub extends Model
{
    protected $table = 'directivos_club';

    protected $fillable = ['club_id', 'nombre', 'cargo', 'firma_digital_url'];

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
