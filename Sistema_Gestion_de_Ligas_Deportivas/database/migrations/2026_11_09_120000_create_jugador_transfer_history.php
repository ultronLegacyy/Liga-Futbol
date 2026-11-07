<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE jugadores_equipos MODIFY estado_elegibilidad ENUM('Activo', 'Inelegible', 'Suspendido', 'Transferido') NOT NULL DEFAULT 'Activo'");
        }

        Schema::create('historial_transferencias_jugador', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('jugador_origen_id')->constrained('jugadores_equipos')->restrictOnDelete();
            $table->foreignId('jugador_destino_id')->constrained('jugadores_equipos')->restrictOnDelete();
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
            $table->foreign('transferido_por_usuario_id', 'htj_transferido_por_fk')
                ->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_transferencias_jugador');

        DB::table('jugadores_equipos')
            ->where('estado_elegibilidad', 'Transferido')
            ->update(['estado_elegibilidad' => 'Inelegible']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE jugadores_equipos MODIFY estado_elegibilidad ENUM('Activo', 'Inelegible', 'Suspendido') NOT NULL DEFAULT 'Activo'");
        }
    }
};
