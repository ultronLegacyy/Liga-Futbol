<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Cada temporada tiene sus propias categorías (RF-02, Tabla 4 de la ERS): una temporada cerrada conserva las suyas intactas
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            // Admiten NULL solo por los datos anteriores a esta migración; la aplicación siempre los exige.
            // Borrar una temporada con categorías lo impide la base, en lugar de borrarlas en cascada
            $table->foreignId('temporada_id')->nullable()->after('id')->constrained('temporadas')->restrictOnDelete();
            $table->enum('rama', ['Libre', 'Veteranos', 'Femenil', 'Infantil'])->nullable()->after('nombre');
            $table->unsignedSmallInteger('anio_nacimiento_desde')->nullable()->after('rama');
            $table->unsignedSmallInteger('anio_nacimiento_hasta')->nullable()->after('anio_nacimiento_desde');

            // «Juvenil» existe en más de una rama: el nombre se repite solo en otra rama u otra temporada
            $table->unique(['temporada_id', 'rama', 'nombre']);
        });

        // Las categorías que ya existían toman la temporada de sus grupos
        $temporadasPorCategoria = DB::table('grupos')
            ->select('categoria_id', DB::raw('MIN(temporada_id) as temporada_id'))
            ->groupBy('categoria_id')
            ->get();

        foreach ($temporadasPorCategoria as $fila) {
            DB::table('categorias')
                ->where('id', $fila->categoria_id)
                ->whereNull('temporada_id')
                ->update(['temporada_id' => $fila->temporada_id]);
        }
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropForeign(['temporada_id']);
            $table->dropUnique(['temporada_id', 'rama', 'nombre']);
            $table->dropColumn(['temporada_id', 'rama', 'anio_nacimiento_desde', 'anio_nacimiento_hasta']);
        });
    }
};
