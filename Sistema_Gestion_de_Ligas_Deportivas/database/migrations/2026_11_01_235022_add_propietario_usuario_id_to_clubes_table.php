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
        Schema::table('clubes', function (Blueprint $table) {
            $table->foreignId('propietario_usuario_id')
                ->nullable()
                ->after('id')
                ->constrained('usuarios')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clubes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('propietario_usuario_id');
        });
    }
};