<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temporada extends Model
{
    use HasFactory;
    protected $table = 'temporadas';
    protected $fillable = ['liga_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'estado'];
}
