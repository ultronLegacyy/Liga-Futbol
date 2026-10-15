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
        // 13. directivos_club
        Schema::create('directivos_club', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubes')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('cargo');
            $table->string('firma_digital_url')->nullable();
            $table->timestamps();
        });

        // 14. historial_ventas_club
        Schema::create('historial_ventas_club', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubes')->cascadeOnDelete();
            $table->string('propietario_anterior');
            $table->string('propietario_nuevo');
            $table->string('carta_venta_pdf')->nullable();
            $table->date('fecha_venta');
            $table->timestamps();
        });

        // 15. equipos
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubes')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->string('nombre');
            $table->integer('partidos_default_acumulados')->default(0);
            $table->timestamps();
        });

        // 16. personas
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellidos');
            $table->date('fecha_nacimiento');
            $table->string('curp', 18)->unique();
            $table->timestamps();
        });

        // 17. documentos_persona
        Schema::create('documentos_persona', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->enum('tipo', ['INE', 'CURP', 'Foto']);
            $table->string('ruta_storage');
            $table->timestamp('validado_en')->nullable();
            $table->timestamps();
        });

        // 18. jugadores_equipos (Roster)
        Schema::create('jugadores_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->unsignedInteger('dorsal')->nullable();
            $table->enum('estado_elegibilidad', ['Activo', 'Inelegible', 'Suspendido'])->default('Activo');
            $table->timestamps();
        });

        // 19. arbitros
        Schema::create('arbitros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->unique()->constrained('personas')->cascadeOnDelete();
            $table->string('colegio_pertenencia')->nullable();
            $table->decimal('tarifa_por_partido', 10, 2)->default(0.00);
            $table->enum('estado', ['Activo', 'Inactivo'])->default('Activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arbitros');
        Schema::dropIfExists('jugadores_equipos');
        Schema::dropIfExists('documentos_persona');
        Schema::dropIfExists('personas');
        Schema::dropIfExists('equipos');
        Schema::dropIfExists('historial_ventas_club');
        Schema::dropIfExists('directivos_club');
    }
};
