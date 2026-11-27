<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-060 (RF-11.6): un grupo tiene llaves en varias fases (Liguilla, Consolación y Campeón de Campeones), así que el
 * número de llave es único dentro de la fase y no en todo el grupo. El índice nuevo empieza por grupo_id para que la
 * llave foránea del grupo siga teniendo índice.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('llaves_liguilla', function (Blueprint $table) {
            $table->unique(['grupo_id', 'fase_id', 'orden']);
            $table->dropUnique(['grupo_id', 'orden']);
        });
    }

    public function down(): void
    {
        // Sin el índice por fase solo caben las llaves de la Liguilla
        DB::table('llaves_liguilla')
            ->whereIn('fase_id', DB::table('fases_torneo')->where('tipo', '!=', 'Liguilla')->select('id'))
            ->delete();

        Schema::table('llaves_liguilla', function (Blueprint $table) {
            $table->unique(['grupo_id', 'orden']);
            $table->dropUnique(['grupo_id', 'fase_id', 'orden']);
        });
    }
};
