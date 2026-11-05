<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Roles de cada persona por temporada (HU-013, RF-03.2): la persona es una sola identidad y aquí se registra qué papel tiene en cada temporada
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('persona_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->enum('rol', ['jugador', 'delegado', 'arbitro', 'directivo']);
            // Borrar una temporada con roles lo impide la base, igual que con sus categorías
            $table->foreignId('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->timestamps();

            // Una persona no repite el mismo rol en la misma temporada
            $table->unique(['persona_id', 'rol', 'temporada_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persona_roles');
    }
};
