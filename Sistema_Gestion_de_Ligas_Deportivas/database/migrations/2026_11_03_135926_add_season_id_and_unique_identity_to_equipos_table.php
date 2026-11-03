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
        Schema::table('equipos', function (Blueprint $table) {
            $table->unsignedBigInteger('temporada_id')->nullable()->after('club_id');
        });

        DB::table('equipos')
            ->join('grupos', 'equipos.grupo_id', '=', 'grupos.id')
            ->update(['equipos.temporada_id' => DB::raw('grupos.temporada_id')]);

        Schema::table('equipos', function (Blueprint $table) {
            $table->foreign('temporada_id')->references('id')->on('temporadas')->restrictOnDelete();
            $table->unique(['club_id', 'temporada_id', 'nombre'], 'equipos_club_temporada_nombre_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->index('club_id', 'equipos_club_id_index');
        });

        Schema::table('equipos', function (Blueprint $table) {
            $table->dropUnique('equipos_club_temporada_nombre_unique');
            $table->dropForeign(['temporada_id']);
            $table->dropColumn('temporada_id');
        });
    }
};
