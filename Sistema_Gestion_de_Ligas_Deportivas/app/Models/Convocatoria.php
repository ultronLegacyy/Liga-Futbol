<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convocatoria extends Model {
    use HasFactory;
    protected $table = 'convocatorias';
    protected $fillable = ['temporada_id', 'archivo_pdf', 'reglas_versionadas'];
}