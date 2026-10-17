<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipo extends Model
{
    use HasFactory;

    // Nombre de la tabla de persistencia relacional
    protected $table = 'equipos';

    // Atributos asignables de forma masiva
    protected $fillable = [
        'club_id',
        'grupo_id',
        'nombre',
        'partidos_default_acumulados',
    ];

    // Relacion muchos a uno con el club propietario del equipo
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
}
