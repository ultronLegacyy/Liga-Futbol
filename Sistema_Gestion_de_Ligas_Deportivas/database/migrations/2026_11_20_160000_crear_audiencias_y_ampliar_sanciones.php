<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     * HU-045 (RF-10.2): Registro de audiencia, resolución y sanción.
     * HU-046 (RF-10.3, RNF-44): Cálculo de vigencia de sanción y mantención del boletín de castigados.
     * HU-047 (RF-10.4, IU-05): Bloqueo automático de participación o programación por sanción vigente.
     */
    public function up(): void
    {
        // 1. Tabla de audiencias disciplinarias (HU-045 / RF-10.2)
        if (! Schema::hasTable('audiencias_disciplinarias')) {
            Schema::create('audiencias_disciplinarias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expediente_id')->constrained('expedientes_disciplinarios')->cascadeOnDelete();
                $table->dateTime('fecha_audiencia');
                $table->string('lugar_o_medio', 150)->nullable();
                $table->text('comparecientes');
                $table->text('alegatos_defensa');
                $table->text('resolucion')->nullable();
                $table->foreignId('registrado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 2. Ampliación de la tabla de sanciones (HU-045, HU-046, HU-047)
        Schema::table('sanciones', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->change();
            $table->foreignId('equipo_id')->nullable()->after('persona_id')->constrained('equipos')->nullOnDelete();
            $table->foreignId('articulo_id')->nullable()->after('equipo_id')->constrained('catalogo_articulos')->nullOnDelete();
            $table->string('tipo_sancion', 50)->default('partidos_suspension')->after('articulo_id');
            $table->date('fecha_inicio')->nullable()->after('tipo_sancion');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            $table->foreignId('partido_suspension_hasta_id')->nullable()->after('partidos_cumplidos')->constrained('partidos')->nullOnDelete();
            $table->decimal('monto_multa', 10, 2)->nullable()->after('partido_suspension_hasta_id');
            $table->text('motivo_resolucion')->nullable()->after('descripcion_sancion');
            $table->boolean('publicada_en_boletin')->default(false)->after('estado');
            $table->foreignId('validado_por_usuario_id')->nullable()->after('publicada_en_boletin')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('fecha_validacion')->nullable()->after('validado_por_usuario_id');
            $table->foreignId('autorizado_por_usuario_id')->nullable()->after('fecha_validacion')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('fecha_publicacion')->nullable()->after('autorizado_por_usuario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanciones', function (Blueprint $table) {
            $table->dropForeign(['autorizado_por_usuario_id']);
            $table->dropForeign(['validado_por_usuario_id']);
            $table->dropForeign(['partido_suspension_hasta_id']);
            $table->dropForeign(['articulo_id']);
            $table->dropForeign(['equipo_id']);

            $table->dropColumn([
                'equipo_id',
                'articulo_id',
                'tipo_sancion',
                'fecha_inicio',
                'fecha_fin',
                'partido_suspension_hasta_id',
                'monto_multa',
                'motivo_resolucion',
                'publicada_en_boletin',
                'validado_por_usuario_id',
                'fecha_validacion',
                'autorizado_por_usuario_id',
                'fecha_publicacion',
            ]);

            $table->foreignId('persona_id')->nullable(false)->change();
        });

        Schema::dropIfExists('audiencias_disciplinarias');
    }
};
