<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-037 (SCRUM-49, RF-08.1): de dónde viene la cédula (captura en campo o transcripción de la cédula en papel) y quién la transcribió
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cedulas_arbitrales', function (Blueprint $table) {
            $table->string('origen', 10)->default('campo')->after('estado');
            $table->foreignId('capturada_por_id')->nullable()->after('origen')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('capturada_en')->nullable()->after('capturada_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('cedulas_arbitrales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('capturada_por_id');
            $table->dropColumn(['origen', 'capturada_en']);
        });
    }
};
