<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-056 (RF-11.2): cada llave se juega en un partido de una jornada de liguilla.
 * - jornadas.fase_id: la jornada de una fase final; sin fase es de la fase regular y solo esa cuenta para la tabla.
 * - llaves_liguilla.ronda y partido_id: la ronda de la llave y su partido (uno por llave).
 * - equipo_visitante_id admite nulo: el pase directo de los mejores ubicados en la primera ronda (decisión de Daniel, 25/11).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('jornadas', function (Blueprint $table) {
            $table->foreignId('fase_id')->nullable()->after('grupo_id')->constrained('fases_torneo')->cascadeOnDelete();
        });

        Schema::table('llaves_liguilla', function (Blueprint $table) {
            $table->unsignedInteger('ronda')->default(1)->after('orden');
            $table->foreignId('partido_id')->nullable()->unique()->after('ronda')->constrained('partidos')->nullOnDelete();
            $table->foreignId('equipo_visitante_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Los pases directos no tienen rival: sin ellos la columna vuelve a ser obligatoria
        DB::table('llaves_liguilla')->whereNull('equipo_visitante_id')->delete();

        Schema::table('llaves_liguilla', function (Blueprint $table) {
            $table->foreignId('equipo_visitante_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('partido_id');
            $table->dropColumn('ronda');
        });

        Schema::table('jornadas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fase_id');
        });
    }
};
