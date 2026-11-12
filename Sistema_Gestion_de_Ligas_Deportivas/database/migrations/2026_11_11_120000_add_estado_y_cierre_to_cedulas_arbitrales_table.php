<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-036 y HU-075: la cédula está abierta mientras se captura; al cerrarse guarda quién y cuándo
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cedulas_arbitrales', function (Blueprint $table) {
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta')->after('partido_id');
            $table->foreignId('cerrada_por_id')->nullable()->after('firma_capitan_visitante')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('cerrada_en')->nullable()->after('cerrada_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('cedulas_arbitrales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cerrada_por_id');
            $table->dropColumn(['estado', 'cerrada_en']);
        });
    }
};
