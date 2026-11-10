<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
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
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('jugadores_equipos')->where('estado_elegibilidad', 'Baja')->exists()) {
            throw new RuntimeException('No se puede revertir SCRUM-150 mientras existan inscripciones dadas de baja.');
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
