<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-065 (RF-15.2): qué recordatorio de cada cargo ya se mandó, para no repetirlo (decisión 4 de Daniel)
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('recordatorios_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargos_financieros')->cascadeOnDelete();
            // «proximo» (vence en 3 días o menos) o «vencido»
            $table->string('etapa', 10);
            $table->timestamps();

            $table->unique(['cargo_id', 'etapa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios_pago');
    }
};
