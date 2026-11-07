<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE jugadores_equipos MODIFY estado_elegibilidad ENUM('Activo', 'Inelegible', 'Suspendido', 'Baja') NOT NULL DEFAULT 'Activo'");
        }

        Schema::table('jugadores_equipos', function (Blueprint $table): void {
            $table->text('motivo_baja')->nullable();
            $table->date('fecha_baja')->nullable();
        });
    }

    public function down(): void
    {
        DB::table('jugadores_equipos')
            ->where('estado_elegibilidad', 'Baja')
            ->update(['estado_elegibilidad' => 'Inelegible']);

        Schema::table('jugadores_equipos', function (Blueprint $table): void {
            $table->dropColumn(['motivo_baja', 'fecha_baja']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE jugadores_equipos MODIFY estado_elegibilidad ENUM('Activo', 'Inelegible', 'Suspendido') NOT NULL DEFAULT 'Activo'");
        }
    }
};
