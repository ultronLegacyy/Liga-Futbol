<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-017, criterio 3: historial de transferencias entre clubes.
 *
 * Las referencias a las inscripciones usan nullOnDelete: la venta de un club (HU-011,
 * criterio 2) borra las inscripciones de su plantilla «conservando el histórico anterior»,
 * así que la fila del historial se conserva con la persona, los clubes, los equipos y la
 * temporada aunque desaparezca la inscripción.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('historial_transferencias_jugador', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('jugador_origen_id')->nullable()->constrained('jugadores_equipos')->nullOnDelete();
            $table->foreignId('jugador_destino_id')->nullable()->constrained('jugadores_equipos')->nullOnDelete();
            $table->foreignId('club_origen_id')->constrained('clubes')->restrictOnDelete();
            $table->foreignId('club_destino_id')->constrained('clubes')->restrictOnDelete();
            $table->foreignId('equipo_origen_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('equipo_destino_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('temporada_id')->constrained('temporadas')->restrictOnDelete();
            $table->foreignId('transferido_por_usuario_id')->nullable();
            $table->timestamps();
            $table->index(['persona_id', 'temporada_id']);
        });

        Schema::table('historial_transferencias_jugador', function (Blueprint $table): void {
            $table->foreign('transferido_por_usuario_id', 'htj_transferido_por_fk')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    /**
     * Se niega a revertir si hay transferencias registradas, para no perder el historial.
     * Para revertir en un servidor con datos, primero hay que respaldar la tabla.
     */
    public function down(): void
    {
        if (Schema::hasTable('historial_transferencias_jugador') && DB::table('historial_transferencias_jugador')->exists()) {
            throw new RuntimeException('No se puede revertir SCRUM-32 mientras exista historial de transferencias.');
        }

        Schema::dropIfExists('historial_transferencias_jugador');
    }
};
