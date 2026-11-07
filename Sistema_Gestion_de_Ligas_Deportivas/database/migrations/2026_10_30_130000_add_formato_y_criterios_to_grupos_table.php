<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Formato de competencia y criterios de clasificación de cada grupo (HU-008, RF-02.3).
// Los puntos por resultado y los desempates siguen en las reglas de la temporada (HU-006, RF-01.2)
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            // Admiten NULL porque los grupos existentes aún no tienen configuración
            $table->string('formato_competencia', 50)->nullable()->after('nombre');
            $table->json('criterios_clasificacion')->nullable()->after('formato_competencia');
        });
    }

    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->dropColumn(['formato_competencia', 'criterios_clasificacion']);
        });
    }
};
