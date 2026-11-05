<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Un árbitro no puede tener dos partidos a la misma fecha y hora (HU-026, CA3).
// La base lo garantiza aun con asignaciones simultáneas; los partidos sin árbitro (NULL) no cuentan
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->unique(['arbitro_id', 'fecha_partido', 'hora_partido'], 'partidos_arbitro_horario_unique');
        });
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropUnique('partidos_arbitro_horario_unique');
        });
    }
};
