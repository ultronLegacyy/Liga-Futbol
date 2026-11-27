<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-040 (RF-08.4): un resultado capturado solo es oficial cuando alguien con resultados.validar lo valida.
// Se guarda quién y cuándo; sin validar, el resultado no cuenta para la tabla de posiciones (HU-041)
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cedulas_arbitrales', function (Blueprint $table) {
            $table->foreignId('validada_por_id')->nullable()->after('cerrada_en')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('validada_en')->nullable()->after('validada_por_id');
            $table->index(['estado', 'validada_en']);
        });
    }

    public function down(): void
    {
        Schema::table('cedulas_arbitrales', function (Blueprint $table) {
            $table->dropIndex(['estado', 'validada_en']);
            $table->dropConstrainedForeignId('validada_por_id');
            $table->dropColumn('validada_en');
        });
    }
};
