<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-053 (RF-14.1): convocatoria a asamblea y lista de asistencia de los delegados.
 *
 * Amplía la tabla asambleas del esquema base (fecha, acta y acuerdos en texto libre)
 * con los datos de la convocatoria, y agrega una fila de asistencia por club.
 * Las asambleas que ya existían se marcan como Celebradas: ya tienen acta.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('asambleas', function (Blueprint $table): void {
            $table->string('tipo', 20)->default('Ordinaria')->after('id');
            $table->time('hora')->nullable()->after('fecha');
            $table->string('lugar', 200)->nullable()->after('hora');
            $table->text('orden_del_dia')->nullable()->after('lugar');
            $table->string('estado', 20)->default('Convocada')->after('orden_del_dia');
            $table->foreignId('temporada_id')->nullable()->after('estado')->constrained('temporadas')->nullOnDelete();
            $table->foreignId('convocada_por_usuario_id')->nullable()->after('temporada_id')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('asistencia_registrada_en')->nullable()->after('convocada_por_usuario_id');
            $table->index(['estado', 'fecha']);
        });

        DB::table('asambleas')->update(['estado' => 'Celebrada']);

        Schema::create('asistencias_asamblea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asamblea_id')->constrained('asambleas')->restrictOnDelete();
            $table->foreignId('club_id')->constrained('clubes')->restrictOnDelete();
            $table->foreignId('delegado_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('nombre_asistente', 200)->nullable();
            $table->boolean('asistio')->default(false);
            $table->string('observaciones', 255)->nullable();
            $table->foreignId('registrado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['asamblea_id', 'club_id']);
        });
    }

    /** Se niega a revertir si hay asistencias registradas: son constancia de la asamblea. */
    public function down(): void
    {
        if (Schema::hasTable('asistencias_asamblea') && DB::table('asistencias_asamblea')->exists()) {
            throw new RuntimeException('No se puede revertir SCRUM-83 mientras existan asistencias registradas.');
        }

        Schema::dropIfExists('asistencias_asamblea');

        Schema::table('asambleas', function (Blueprint $table): void {
            $table->dropIndex(['estado', 'fecha']);
            $table->dropConstrainedForeignId('convocada_por_usuario_id');
            $table->dropConstrainedForeignId('temporada_id');
            $table->dropColumn(['tipo', 'hora', 'lugar', 'orden_del_dia', 'estado', 'asistencia_registrada_en']);
        });
    }
};
