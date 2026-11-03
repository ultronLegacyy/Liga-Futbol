<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    protected $table = 'personas';

    protected $fillable = ['nombre', 'apellidos', 'fecha_nacimiento', 'curp'];

    /** @return HasMany<Jugador, $this> */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Jugador::class, 'persona_id');
    }
}
