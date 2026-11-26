<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Índices para acotar consultas de ocupación de canchas y bloqueos en volúmenes multi-temporada (RNF-08 / HU-074).
     */
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->index(['fecha_partido', 'estado'], 'partidos_fecha_estado_idx');
        });

        Schema::table('bloqueos_cancha', function (Blueprint $table) {
            $table->index(['fecha_inicio', 'fecha_fin'], 'bloqueos_cancha_ventana_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bloqueos_cancha', function (Blueprint $table) {
            $table->dropIndex('bloqueos_cancha_ventana_idx');
        });

        Schema::table('partidos', function (Blueprint $table) {
            $table->dropIndex('partidos_fecha_estado_idx');
        });
    }
};
