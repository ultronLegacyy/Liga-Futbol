<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-036: alineación de cada equipo en la cédula; el árbitro marca asistencia, número de camiseta y capitán
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('alineaciones_partido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cedula_id')->constrained('cedulas_arbitrales')->cascadeOnDelete();
            $table->foreignId('jugador_id')->constrained('jugadores_equipos')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->boolean('asistio')->default(false);
            $table->unsignedSmallInteger('numero_camiseta')->nullable();
            $table->boolean('es_capitan')->default(false);
            // RNF-21: clave del último cambio aplicado; un reenvío con la misma clave no cambia nada
            $table->char('clave_envio', 36)->nullable()->unique();
            $table->timestamps();

            $table->unique(['cedula_id', 'jugador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alineaciones_partido');
    }
};
