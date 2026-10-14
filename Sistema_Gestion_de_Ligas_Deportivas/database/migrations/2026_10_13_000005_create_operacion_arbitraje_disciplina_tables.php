<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 26. cedulas_arbitrales
        Schema::create('cedulas_arbitrales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partido_id')->unique()->constrained('partidos')->cascadeOnDelete();
            $table->unsignedInteger('goles_local')->default(0);
            $table->unsignedInteger('goles_visitante')->default(0);
            $table->text('informe_arbitral')->nullable();
            $table->string('firma_capitan_local')->nullable();
            $table->string('firma_capitan_visitante')->nullable();
            $table->timestamps();
        });

        // 27. eventos_partido
        Schema::create('eventos_partido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cedula_id')->constrained('cedulas_arbitrales')->cascadeOnDelete();
            $table->foreignId('jugador_id')->nullable()->constrained('jugadores_equipos')->nullOnDelete();
            $table->enum('tipo', ['Gol', 'Amarilla', 'Roja']);
            $table->unsignedInteger('minuto')->nullable();
            $table->timestamps();
        });

        // 28. catalogo_articulos
        Schema::create('catalogo_articulos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->text('descripcion');
            $table->unsignedInteger('castigo_base_partidos')->default(1);
            $table->timestamps();
        });

        // 29. expedientes_disciplinarios
        Schema::create('expedientes_disciplinarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cedula_id')->nullable()->constrained('cedulas_arbitrales')->nullOnDelete();
            $table->foreignId('partido_id')->nullable()->constrained('partidos')->nullOnDelete();
            $table->foreignId('articulo_id')->constrained('catalogo_articulos')->cascadeOnDelete();
            $table->enum('estado', ['Abierto', 'Resuelto'])->default('Abierto');
            $table->timestamps();
        });

        // 30. sanciones
        Schema::create('sanciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes_disciplinarios')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->text('descripcion_sancion')->nullable();
            $table->unsignedInteger('partidos_suspendidos')->default(0);
            $table->unsignedInteger('partidos_cumplidos')->default(0);
            $table->enum('estado', ['Vigente', 'Cumplida', 'Cancelada'])->default('Vigente');
            $table->timestamps();
        });

        // 31. recursos_inconformidad
        Schema::create('recursos_inconformidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sancion_id')->constrained('sanciones')->cascadeOnDelete();
            $table->foreignId('club_apelante_id')->constrained('clubes')->cascadeOnDelete();
            $table->string('evidencia_pdf')->nullable();
            $table->text('resolucion')->nullable();
            $table->enum('estado', ['Pendiente', 'Admitido', 'Rechazado'])->default('Pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recursos_inconformidad');
        Schema::dropIfExists('sanciones');
        Schema::dropIfExists('expedientes_disciplinarios');
        Schema::dropIfExists('catalogo_articulos');
        Schema::dropIfExists('eventos_partido');
        Schema::dropIfExists('cedulas_arbitrales');
    }
};
