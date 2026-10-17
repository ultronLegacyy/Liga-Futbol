<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convocatoria extends Model
{
    use HasFactory;
    use Auditable;
    protected $table = 'convocatorias';
    protected $fillable = ['temporada_id', 'archivo_pdf', 'reglas_versionadas'];
}
