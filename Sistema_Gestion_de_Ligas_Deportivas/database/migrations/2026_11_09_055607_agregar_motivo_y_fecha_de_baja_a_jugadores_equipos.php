<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-017, criterio 1: la baja guarda su motivo y su fecha. Agrega Baja a los estados
 * de la inscripción (Activo, Inelegible, Suspendido, Baja: los 4 de HU-018).
 */
return new class () extends Migration {
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE jugadores_equipos MODIFY estado_elegibilidad ENUM('Activo', 'Inelegible', 'Suspendido', 'Baja') NOT NULL DEFAULT 'Activo'");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('jugadores_equipos', function (Blueprint $table): void {
                $table->string('estado_elegibilidad')->default('Activo')->change();
            });
        }

        Schema::table('jugadores_equipos', function (Blueprint $table): void {
            $table->text('motivo_baja')->nullable();
            $table->date('fecha_baja')->nullable();
        });
    }

    /**
     * Se niega a revertir si hay bajas registradas: quitar Baja del enum las perdería.
     * Para revertir en un servidor con datos, primero hay que respaldar y resolver esas bajas.
     */
    public function down(): void
    {
        if (DB::table('jugadores_equipos')->where('estado_elegibilidad', 'Baja')->exists()) {
            throw new RuntimeException('No se puede revertir SCRUM-32 mientras existan inscripciones dadas de baja.');
        }

        Schema::table('jugadores_equipos', function (Blueprint $table): void {
            $table->dropColumn(['motivo_baja', 'fecha_baja']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE jugadores_equipos MODIFY estado_elegibilidad ENUM('Activo', 'Inelegible', 'Suspendido') NOT NULL DEFAULT 'Activo'");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('jugadores_equipos', function (Blueprint $table): void {
                $table->enum('estado_elegibilidad', ['Activo', 'Inelegible', 'Suspendido'])->default('Activo')->change();
            });
        }
    }
};
