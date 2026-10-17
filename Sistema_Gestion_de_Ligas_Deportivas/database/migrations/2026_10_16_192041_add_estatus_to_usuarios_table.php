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
        Schema::table('usuarios', function (Blueprint $table) {
            // Incorporacion del campo estatus para control de acceso y suspension de cuentas
            $table->enum('estatus', ['activo', 'inactivo', 'suspendido'])->default('activo')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Eliminacion de la columna estatus en caso de revertir la migracion
            $table->dropColumn('estatus');
        });
    }
};
