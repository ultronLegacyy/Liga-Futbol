<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('regla_temporadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temporada_id')->constrained('temporadas')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('reglas');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('motivo', 1000);
            $table->timestamps();

            $table->unique(['temporada_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regla_temporadas');
    }
};
