<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Cambia el valor por omisión de capacidad diaria de canchas de 1 a 4
     * y actualiza canchas existentes que conservaban el valor inicial 1 (HU-023 / PO).
     */
    public function up(): void
    {
        DB::table('canchas')
            ->where('capacidad_partidos_consecutivos', 1)
            ->update(['capacidad_partidos_consecutivos' => 4]);

        Schema::table('canchas', function (Blueprint $table): void {
            $table->unsignedInteger('capacidad_partidos_consecutivos')->default(4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('canchas', function (Blueprint $table): void {
            $table->unsignedInteger('capacidad_partidos_consecutivos')->default(1)->change();
        });
    }
};
