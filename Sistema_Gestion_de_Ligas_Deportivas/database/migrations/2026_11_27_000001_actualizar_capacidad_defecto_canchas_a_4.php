<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Cambia el valor por omisión de capacidad diaria de canchas de 1 a 4 (HU-023 / PO)
     * sin alterar registros ya capturados en base de datos.
     */
    public function up(): void
    {
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
