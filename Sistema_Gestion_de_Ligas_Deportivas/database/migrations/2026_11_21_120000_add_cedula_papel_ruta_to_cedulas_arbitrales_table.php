<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-037 (SCRUM-49, respuesta 50 del PO): la cédula en papel se cierra con su escaneo firmado por el árbitro y los capitanes
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cedulas_arbitrales', function (Blueprint $table) {
            $table->string('cedula_papel_ruta')->nullable()->after('firma_capitan_visitante');
        });
    }

    public function down(): void
    {
        Schema::table('cedulas_arbitrales', function (Blueprint $table) {
            $table->dropColumn('cedula_papel_ruta');
        });
    }
};
