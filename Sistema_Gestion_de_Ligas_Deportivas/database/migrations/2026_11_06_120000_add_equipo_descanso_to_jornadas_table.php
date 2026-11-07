<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-027 (RF-06.1, SCRUM-42) y HU-028 (RF-06.2, SCRUM-43): equipo que descansa y unicidad de jornada por grupo
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('jornadas', function (Blueprint $table) {
            $table->foreignId('equipo_descanso_id')->nullable()->after('numero_jornada')
                ->constrained('equipos')->nullOnDelete();
            $table->unique(['grupo_id', 'numero_jornada'], 'jornadas_grupo_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jornadas', function (Blueprint $table) {
            $table->dropUnique('jornadas_grupo_numero_unique');
            $table->dropForeign(['equipo_descanso_id']);
            $table->dropColumn('equipo_descanso_id');
        });
    }
};
