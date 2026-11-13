<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-041 (RF-09.1): la tabla guarda la posición calculada y la versión de las reglas de la temporada que se usó.
// Un equipo aparece una sola vez en la tabla de su grupo
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tablas_posiciones', function (Blueprint $table) {
            $table->unsignedInteger('posicion')->nullable()->after('equipo_id');
            $table->unsignedInteger('regla_version')->nullable()->after('puntos');
            $table->unique(['grupo_id', 'equipo_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tablas_posiciones', function (Blueprint $table) {
            // La llave foránea de grupo_id usa el índice único; se recrea uno simple antes de quitarlo
            $table->index('grupo_id');
            $table->dropUnique(['grupo_id', 'equipo_id']);
            $table->dropColumn(['posicion', 'regla_version']);
        });
    }
};
