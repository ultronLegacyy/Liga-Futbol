<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-043 CA2 (RF-09.3): copia histórica de la tabla de posiciones al cierre de cada jornada.
// Se guarda una vez y no se reescribe, para consultar cómo iba el grupo en esa jornada
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tablas_posiciones_jornada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->foreignId('jornada_id')->constrained('jornadas')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->unsignedInteger('posicion');
            $table->unsignedInteger('pj')->default(0);
            $table->unsignedInteger('pg')->default(0);
            $table->unsignedInteger('pe')->default(0);
            $table->unsignedInteger('pp')->default(0);
            $table->unsignedInteger('gf')->default(0);
            $table->unsignedInteger('gc')->default(0);
            $table->integer('dg')->default(0);
            $table->unsignedInteger('puntos')->default(0);
            $table->unsignedInteger('regla_version');
            $table->timestamps();

            $table->unique(['jornada_id', 'equipo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablas_posiciones_jornada');
    }
};
