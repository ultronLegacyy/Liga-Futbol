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
        // 20. canchas
        Schema::create('canchas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('direccion_ubicacion')->nullable();
            $table->unsignedInteger('capacidad_partidos_consecutivos')->default(1);
            $table->enum('estado', ['Disponible', 'Mantenimiento', 'Inactiva'])->default('Disponible');
            $table->timestamps();
        });

        // 21. bloqueos_cancha
        Schema::create('bloqueos_cancha', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancha_id')->constrained('canchas')->cascadeOnDelete();
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->string('motivo');
            $table->timestamps();
        });

        // 22. prestamos_cancha
        Schema::create('prestamos_cancha', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancha_id')->constrained('canchas')->cascadeOnDelete();
            $table->foreignId('club_solicitante_id')->constrained('clubes')->cascadeOnDelete();
            $table->string('formato_prestamo_pdf')->nullable();
            $table->date('vigencia');
            $table->timestamps();
        });

        // 23. preferencias_equipo
        Schema::create('preferencias_equipo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('cancha_id_preferida')->nullable()->constrained('canchas')->nullOnDelete();
            $table->string('dia_preferido')->nullable();
            $table->time('hora_preferida')->nullable();
            $table->timestamps();
        });

        // 24. jornadas
        Schema::create('jornadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->unsignedInteger('numero_jornada');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->timestamps();
        });

        // 25. partidos
        Schema::create('partidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_id')->constrained('jornadas')->cascadeOnDelete();
            $table->foreignId('equipo_local_id')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('equipo_visitante_id')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('cancha_id')->nullable()->constrained('canchas')->nullOnDelete();
            $table->foreignId('arbitro_id')->nullable()->constrained('arbitros')->nullOnDelete();
            $table->date('fecha_partido')->nullable();
            $table->time('hora_partido')->nullable();
            $table->enum('estado', [
                'borrador',
                'programado',
                'confirmado',
                'en juego',
                'finalizado',
                'suspendido'
            ])->default('borrador');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partidos');
        Schema::dropIfExists('jornadas');
        Schema::dropIfExists('preferencias_equipo');
        Schema::dropIfExists('prestamos_cancha');
        Schema::dropIfExists('bloqueos_cancha');
        Schema::dropIfExists('canchas');
    }
};
