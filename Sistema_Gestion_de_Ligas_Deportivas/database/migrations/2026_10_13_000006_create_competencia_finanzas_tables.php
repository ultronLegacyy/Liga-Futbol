<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 32. tablas_posiciones
        Schema::create('tablas_posiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->unsignedInteger('pj')->default(0);
            $table->unsignedInteger('pg')->default(0);
            $table->unsignedInteger('pe')->default(0);
            $table->unsignedInteger('pp')->default(0);
            $table->unsignedInteger('gf')->default(0);
            $table->unsignedInteger('gc')->default(0);
            $table->integer('dg')->default(0);
            $table->unsignedInteger('puntos')->default(0);
            $table->timestamps();
        });

        // 33. llaves_liguilla
        Schema::create('llaves_liguilla', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fase_id')->constrained('fases_torneo')->cascadeOnDelete();
            $table->foreignId('equipo_local_id')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('equipo_visitante_id')->constrained('equipos')->cascadeOnDelete();
            $table->unsignedInteger('marcador_global_local')->default(0);
            $table->unsignedInteger('marcador_global_visitante')->default(0);
            $table->foreignId('avanza_equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
            $table->timestamps();
        });

        // 34. cargos_financieros
        Schema::create('cargos_financieros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubes')->cascadeOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('equipos')->nullOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->enum('concepto', ['Inscripcion', 'Arbitraje', 'Credenciales', 'Multa']);
            $table->decimal('monto', 10, 2);
            $table->date('vencimiento');
            $table->enum('estado', ['Pendiente', 'Pagado', 'Vencido'])->default('Pendiente');
            $table->timestamps();
        });

        // 35. pagos
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargos_financieros')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('metodo_pago');
            $table->string('referencia')->nullable();
            $table->string('recibo_url')->nullable();
            $table->foreignId('validado_por_tesorero_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cargos_financieros');
        Schema::dropIfExists('llaves_liguilla');
        Schema::dropIfExists('tablas_posiciones');
    }
};
