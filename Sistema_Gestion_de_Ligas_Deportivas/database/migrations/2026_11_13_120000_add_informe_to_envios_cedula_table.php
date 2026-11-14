<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// HU-075: el reporte arbitral también viaja por la cola sin conexión; su clave de envío se guarda como las del marcador
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('envios_cedula', function (Blueprint $table) {
            $table->enum('tipo', ['alineacion', 'marcador', 'informe'])->change();
        });
    }

    public function down(): void
    {
        DB::table('envios_cedula')->where('tipo', 'informe')->delete();

        Schema::table('envios_cedula', function (Blueprint $table) {
            $table->enum('tipo', ['alineacion', 'marcador'])->change();
        });
    }
};
