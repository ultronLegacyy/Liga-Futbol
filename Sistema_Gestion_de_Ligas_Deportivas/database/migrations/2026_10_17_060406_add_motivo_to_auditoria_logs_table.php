<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auditoria_logs', function (Blueprint $table) {
            // Motivo que justifica la operación auditada (RF-12.2)
            $table->text('motivo')->nullable()->after('valores_nuevos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auditoria_logs', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
