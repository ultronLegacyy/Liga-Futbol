<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-055 (RF-11.1): cada llave sabe de qué grupo sale, en qué lugar quedó cada equipo y con qué reglas se generó.
// CA2 (RNF-36): se conservan la versión de las reglas de la temporada y los criterios de clasificación del grupo de ese momento
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('llaves_liguilla', function (Blueprint $table) {
            $table->foreignId('grupo_id')->nullable()->after('fase_id')->constrained('grupos')->cascadeOnDelete();
            $table->unsignedInteger('orden')->nullable()->after('grupo_id');
            $table->unsignedInteger('lugar_local')->nullable()->after('equipo_local_id');
            $table->unsignedInteger('lugar_visitante')->nullable()->after('equipo_visitante_id');
            $table->foreignId('regla_temporada_id')->nullable()->after('avanza_equipo_id')->constrained('regla_temporadas')->restrictOnDelete();
            $table->unsignedInteger('regla_version')->nullable()->after('regla_temporada_id');
            $table->json('criterios_clasificacion')->nullable()->after('regla_version');
            $table->foreignId('generada_por_id')->nullable()->after('criterios_clasificacion')->constrained('usuarios')->nullOnDelete();

            $table->unique(['grupo_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::table('llaves_liguilla', function (Blueprint $table) {
            $table->index('grupo_id');
            $table->dropUnique(['grupo_id', 'orden']);
            $table->dropConstrainedForeignId('generada_por_id');
            $table->dropConstrainedForeignId('regla_temporada_id');
            $table->dropConstrainedForeignId('grupo_id');
            $table->dropColumn(['orden', 'lugar_local', 'lugar_visitante', 'regla_version', 'criterios_clasificacion']);
        });
    }
};
