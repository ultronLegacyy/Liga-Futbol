<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-036: clave de envío para no duplicar eventos (RNF-21) y autogol (decisión de Daniel del 11/11)
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('eventos_partido', function (Blueprint $table) {
            // Un autogol es un Gol del jugador que lo anotó y cuenta para el equipo rival
            $table->boolean('autogol')->default(false)->after('tipo');
            // Nula solo en eventos anteriores a esta migración; el servicio siempre la llena
            $table->char('clave_envio', 36)->nullable()->unique()->after('minuto');
        });
    }

    public function down(): void
    {
        Schema::table('eventos_partido', function (Blueprint $table) {
            $table->dropUnique(['clave_envio']);
            $table->dropColumn(['autogol', 'clave_envio']);
        });
    }
};
