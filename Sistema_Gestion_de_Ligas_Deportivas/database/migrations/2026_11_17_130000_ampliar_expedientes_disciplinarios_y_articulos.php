<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     * HU-044 / RF-10.1: Ampliación de expedientes disciplinarios para soportar personas,
     * equipos, evidencias e imputación de artículos del catálogo reglamentario (REF-05).
     */
    public function up(): void
    {
        // 1. Ampliación de expedientes_disciplinarios
        Schema::table('expedientes_disciplinarios', function (Blueprint $table) {
            $table->string('folio', 32)->nullable()->unique()->after('id');
            $table->string('titulo')->nullable()->after('partido_id');
            $table->text('descripcion_hechos')->nullable()->after('titulo');
            $table->string('origen', 50)->default('cedula_arbitral')->after('descripcion_hechos');
            $table->boolean('generado_automaticamente')->default(true)->after('origen');
            $table->timestamp('fecha_apertura')->nullable()->after('generado_automaticamente');
            $table->text('observaciones_comision')->nullable()->after('estado');
            // Permitir que articulo_id sea nullable cuando se usen múltiples artículos en expediente_articulos
            $table->foreignId('articulo_id')->nullable()->change();
        });

        // 2. Tabla pivote expediente_articulos (muchos a muchos con catalogo_articulos)
        Schema::create('expediente_articulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes_disciplinarios')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('catalogo_articulos')->cascadeOnDelete();
            $table->string('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['expediente_id', 'articulo_id']);
        });

        // 3. Tabla expediente_involucrados (personas y equipos relacionados en el expediente)
        Schema::create('expediente_involucrados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes_disciplinarios')->cascadeOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
            $table->string('tipo_involucrado', 60)->default('jugador_expulsado'); // jugador_expulsado, jugador_amonestado, cuerpo_tecnico, directivo, porra_equipo, etc.
            $table->text('descripcion_participacion')->nullable();
            $table->timestamps();
        });

        // 4. Tabla expediente_evidencias (tarjetas, reportes arbitrales, actas)
        Schema::create('expediente_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes_disciplinarios')->cascadeOnDelete();
            $table->string('tipo_evidencia', 50); // evento_tarjeta, incidencia_partido, informe_arbitral, anexo_digital
            $table->text('descripcion');
            $table->unsignedInteger('minuto')->nullable();
            $table->foreignId('evento_id')->nullable()->constrained('eventos_partido')->nullOnDelete();
            $table->foreignId('incidencia_id')->nullable()->constrained('incidencias_partido')->nullOnDelete();
            $table->string('archivo_ruta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expediente_evidencias');
        Schema::dropIfExists('expediente_involucrados');
        Schema::dropIfExists('expediente_articulos');

        Schema::table('expedientes_disciplinarios', function (Blueprint $table) {
            $table->dropUnique(['folio']);
            $table->dropColumn([
                'folio',
                'titulo',
                'descripcion_hechos',
                'origen',
                'generado_automaticamente',
                'fecha_apertura',
                'observaciones_comision',
            ]);
        });
    }
};
