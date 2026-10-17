<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temporada extends Model
{
    use HasFactory;
    use Auditable;
    protected $table = 'temporadas';
    protected $fillable = ['liga_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'estado'];
}
