<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// HU-050 (SCRUM-177): la cuota se asocia a su temporada (criterio 1) y el pago de cancha es un concepto propio (criterio 2)
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cargos_financieros', function (Blueprint $table) {
            // Nullable porque ya hay cargos sin temporada; que sea obligatoria lo valida RegistroCargoService
            $table->foreignId('temporada_id')->nullable()->after('persona_id')->index()
                ->constrained('temporadas')->nullOnDelete();

            $table->enum('concepto', ['Inscripcion', 'Arbitraje', 'Credenciales', 'Multa', 'Cancha'])->change();
        });
    }

    public function down(): void
    {
        // Revertir el enum con cargos de cancha los dejaría sin concepto: se detiene en vez de alterar datos
        if (DB::table('cargos_financieros')->where('concepto', 'Cancha')->exists()) {
            throw new RuntimeException('No se puede revertir: hay cargos financieros con concepto Cancha.');
        }

        Schema::table('cargos_financieros', function (Blueprint $table) {
            $table->dropForeign(['temporada_id']);
            $table->dropIndex(['temporada_id']);
            $table->dropColumn('temporada_id');

            $table->enum('concepto', ['Inscripcion', 'Arbitraje', 'Credenciales', 'Multa'])->change();
        });
    }
};
