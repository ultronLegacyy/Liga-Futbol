<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-057 (RF-11.3): propuestas de ascenso y descenso por grupo, versionadas. Cada versión guarda la versión de las
// reglas de la temporada y una copia de su sección ascenso_descenso, para que un cambio posterior no la afecte (CA2)
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('propuestas_ascenso_descenso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->enum('movimiento', ['asciende', 'desciende']);
            $table->unsignedInteger('posicion');
            $table->unsignedInteger('puntos');
            $table->foreignId('regla_temporada_id')->constrained('regla_temporadas')->restrictOnDelete();
            $table->unsignedInteger('regla_version');
            $table->json('reglas_ascenso_descenso');
            $table->foreignId('generada_por_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->unique(['grupo_id', 'version', 'equipo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propuestas_ascenso_descenso');
    }
};
