<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     * HU-029 (SCRUM-44): Estados y reprogramación de partidos.
     * HU-030 (SCRUM-45): Estados de la jornada y publicación semanal.
     * HU-031 (SCRUM-46): Inmutabilidad del historial de partidos con resultados.
     */
    public function up(): void
    {
        // 1. Ampliar estados del partido para admitir cancelado y reprogramado (HU-029 / RF-06.3)
        // Usamos ALTER TABLE para MariaDB / MySQL de forma compatible con la estructura existente
        DB::statement("ALTER TABLE partidos MODIFY COLUMN estado ENUM('borrador', 'programado', 'confirmado', 'en juego', 'finalizado', 'suspendido', 'cancelado', 'reprogramado') NOT NULL DEFAULT 'borrador'");

        // 2. Agregar ciclo de vida y campos de publicación en jornadas (HU-030 / RF-06.5)
        Schema::table('jornadas', function (Blueprint $table) {
            $table->enum('estado', ['borrador', 'en_revision', 'aprobada', 'publicada'])
                ->default('borrador')
                ->after('equipo_descanso_id');
            $table->foreignId('aprobado_por_id')
                ->nullable()
                ->after('estado')
                ->constrained('usuarios')
                ->nullOnDelete();
            $table->dateTime('fecha_aprobacion')->nullable()->after('aprobado_por_id');
            $table->dateTime('fecha_publicacion')->nullable()->after('fecha_aprobacion');
        });

        // 3. Crear tabla de auditoría para reprogramaciones de partidos (HU-029 / RF-06.4 / RNF-22)
        Schema::create('reprogramaciones_partidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partido_id')->constrained('partidos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('estado_anterior', 30);
            $table->string('estado_nuevo', 30);
            $table->foreignId('cancha_anterior_id')->nullable()->constrained('canchas')->nullOnDelete();
            $table->foreignId('cancha_nueva_id')->nullable()->constrained('canchas')->nullOnDelete();
            $table->date('fecha_anterior')->nullable();
            $table->date('fecha_nueva')->nullable();
            $table->time('hora_anterior')->nullable();
            $table->time('hora_nueva')->nullable();
            $table->text('motivo'); // Motivo obligatorio del cambio
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reprogramaciones_partidos');

        if (Schema::hasColumn('jornadas', 'aprobado_por_id')) {
            Schema::table('jornadas', function (Blueprint $table) {
                $table->dropForeign(['aprobado_por_id']);
                $table->dropColumn(['estado', 'aprobado_por_id', 'fecha_aprobacion', 'fecha_publicacion']);
            });
        }

        DB::table('partidos')->whereIn('estado', ['cancelado', 'reprogramado'])->update(['estado' => 'suspendido']);
        DB::statement("ALTER TABLE partidos MODIFY COLUMN estado ENUM('borrador', 'programado', 'confirmado', 'en juego', 'finalizado', 'suspendido') NOT NULL DEFAULT 'borrador'");
    }
};
