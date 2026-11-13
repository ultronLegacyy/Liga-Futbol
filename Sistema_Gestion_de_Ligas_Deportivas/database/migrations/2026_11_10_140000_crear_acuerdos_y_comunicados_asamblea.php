<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-054 (RF-14.2, RF-14.3): acuerdos y comunicados de una asamblea.
 *
 * Un acuerdo indica si modifica el reglamento de la temporada. Convertirlo en regla
 * permanente es una acción aparte que exige autorización y deja quién, cuándo y por qué.
 * Los comunicados de asamblea van en su propia tabla: comunicados_noticias es del portal.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('acuerdos_asamblea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asamblea_id')->constrained('asambleas')->restrictOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->text('descripcion');
            $table->boolean('modifica_reglamento')->default(false);
            $table->foreignId('temporada_id')->nullable()->constrained('temporadas')->nullOnDelete();
            $table->boolean('es_regla_permanente')->default(false);
            $table->foreignId('autorizado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('autorizado_en')->nullable();
            $table->text('motivo_autorizacion')->nullable();
            $table->foreignId('registrado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['asamblea_id', 'numero']);
        });

        Schema::create('comunicados_asamblea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asamblea_id')->constrained('asambleas')->restrictOnDelete();
            $table->string('titulo', 200);
            $table->text('contenido');
            $table->foreignId('registrado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /** Se niega a revertir si hay acuerdos o comunicados: son la constancia formal de la asamblea. */
    public function down(): void
    {
        foreach (['acuerdos_asamblea', 'comunicados_asamblea'] as $tabla) {
            if (Schema::hasTable($tabla) && DB::table($tabla)->exists()) {
                throw new RuntimeException('No se puede revertir SCRUM-84 mientras existan acuerdos o comunicados registrados.');
            }
        }

        Schema::dropIfExists('comunicados_asamblea');
        Schema::dropIfExists('acuerdos_asamblea');
    }
};
