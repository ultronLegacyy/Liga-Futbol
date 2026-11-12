<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-036: incidencias del partido en texto libre, con minuto y equipo opcionales
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('incidencias_partido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cedula_id')->constrained('cedulas_arbitrales')->cascadeOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
            $table->unsignedSmallInteger('minuto')->nullable();
            $table->text('descripcion');
            // RNF-21: un reenvío con la misma clave no duplica la incidencia
            $table->char('clave_envio', 36)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_partido');
    }
};
