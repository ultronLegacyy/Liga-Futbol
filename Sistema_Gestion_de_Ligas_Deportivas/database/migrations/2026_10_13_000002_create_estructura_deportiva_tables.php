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
        // 8. temporadas
        Schema::create('temporadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liga_id')->constrained('ligas')->cascadeOnDelete();
            $table->string('nombre');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('estado', ['borrador', 'activa', 'cerrada'])->default('borrador');
            $table->timestamps();
        });

        // 9. convocatorias
        Schema::create('convocatorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temporada_id')->constrained('temporadas')->cascadeOnDelete();
            $table->string('archivo_pdf')->nullable();
            $table->json('reglas_versionadas')->nullable();
            $table->timestamps();
        });

        // 10. categorias
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedInteger('edad_min')->nullable();
            $table->unsignedInteger('edad_max')->nullable();
            $table->integer('jerarquia_salto')->default(1);
            $table->timestamps();
        });

        // 11. grupos
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->foreignId('temporada_id')->constrained('temporadas')->cascadeOnDelete();
            $table->string('nombre');
            $table->timestamps();
        });

        // 12. fases_torneo
        Schema::create('fases_torneo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temporada_id')->constrained('temporadas')->cascadeOnDelete();
            $table->enum('tipo', ['Regular', 'Liguilla', 'Consolacion', 'CampeonDeCampeones']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fases_torneo');
        Schema::dropIfExists('grupos');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('convocatorias');
        Schema::dropIfExists('temporadas');
    }
};
