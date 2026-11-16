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
            // Nulo si el jugador sale de la plantilla (venta del club, HU-011): la fila del partido se conserva, como en eventos_partido
            $table->foreignId('jugador_id')->nullable()->constrained('jugadores_equipos')->nullOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->boolean('asistio')->default(false);
            $table->unsignedSmallInteger('numero_camiseta')->nullable();
            $table->boolean('es_capitan')->default(false);
            $table->timestamps();

            $table->unique(['cedula_id', 'jugador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alineaciones_partido');
    }
};
