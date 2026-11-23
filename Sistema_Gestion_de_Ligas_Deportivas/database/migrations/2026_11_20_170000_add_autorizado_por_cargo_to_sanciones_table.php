<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Agrega el cargo del autorizador oficial al boletín de castigados (RNF-44).
     */
    public function up(): void
    {
        Schema::table('sanciones', function (Blueprint $table) {
            $table->string('autorizado_por_cargo', 150)->nullable()->after('autorizado_por_usuario_id');
        });
    }

    public function down(): void
    {
        Schema::table('sanciones', function (Blueprint $table) {
            $table->dropColumn('autorizado_por_cargo');
        });
    }
};
