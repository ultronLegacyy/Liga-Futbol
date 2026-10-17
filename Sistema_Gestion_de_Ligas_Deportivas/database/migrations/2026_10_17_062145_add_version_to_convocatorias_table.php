<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            // Número de versión de la convocatoria dentro de su temporada (RF-01.3)
            $table->unsignedInteger('version')->default(1)->after('temporada_id');
        });

        // Las convocatorias existentes se numeran por temporada en orden de alta (id): 1, 2, 3...
        $siguienteVersion = [];

        DB::table('convocatorias')
            ->select(['id', 'temporada_id'])
            ->lazyById()
            ->each(function (object $convocatoria) use (&$siguienteVersion) {
                $version = ($siguienteVersion[$convocatoria->temporada_id] ?? 0) + 1;
                $siguienteVersion[$convocatoria->temporada_id] = $version;

                DB::table('convocatorias')->where('id', $convocatoria->id)->update(['version' => $version]);
            });

        Schema::table('convocatorias', function (Blueprint $table) {
            // Una temporada no puede tener dos convocatorias con el mismo número de versión
            $table->unique(['temporada_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MariaDB descarta el índice propio de la llave foránea de temporada_id porque el índice
        // único lo cubre; se restaura antes de quitarlo, o la llave impide borrarlo (error 1553)
        if (! Schema::hasIndex('convocatorias', ['temporada_id'])) {
            Schema::table('convocatorias', function (Blueprint $table) {
                $table->index('temporada_id', 'convocatorias_temporada_id_foreign');
            });
        }

        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropUnique(['temporada_id', 'version']);
            $table->dropColumn('version');
        });
    }
};
