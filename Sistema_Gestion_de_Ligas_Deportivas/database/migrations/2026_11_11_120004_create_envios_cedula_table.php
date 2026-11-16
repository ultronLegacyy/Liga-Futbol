<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-036 (RNF-21): claves de envío ya aplicadas a la alineación y al marcador. Un reenvío viejo no pisa un cambio más nuevo
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('envios_cedula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cedula_id')->constrained('cedulas_arbitrales')->cascadeOnDelete();
            $table->enum('tipo', ['alineacion', 'marcador']);
            $table->char('clave_envio', 36)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_cedula');
    }
};
