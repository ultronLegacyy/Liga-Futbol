<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-018, criterio 2: cada cambio de estado guarda quién lo generó, por qué y cuándo.
 *
 * jugador_id usa nullOnDelete: la venta de un club (HU-011, criterio 2) borra las
 * inscripciones de su plantilla «conservando el histórico anterior», así que el cambio
 * se conserva ligado a la persona aunque desaparezca la inscripción.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('historial_estados_jugador', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('jugador_id')->nullable()->constrained('jugadores_equipos')->nullOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->text('motivo');
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['jugador_id', 'created_at']);
            $table->index(['persona_id', 'created_at']);
        });
    }

    /**
     * Se niega a revertir si hay cambios registrados, para no perder el historial.
     * Para revertir en un servidor con datos, primero hay que respaldar la tabla.
     */
    public function down(): void
    {
        if (Schema::hasTable('historial_estados_jugador') && DB::table('historial_estados_jugador')->exists()) {
            throw new RuntimeException('No se puede revertir SCRUM-33 mientras existan cambios de estado registrados.');
        }

        Schema::dropIfExists('historial_estados_jugador');
    }
};
