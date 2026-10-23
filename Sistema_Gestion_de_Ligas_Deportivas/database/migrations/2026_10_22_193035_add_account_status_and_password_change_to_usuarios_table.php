<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->boolean('requires_password_change')
                  ->default(false)
                  ->after('club_id')
                  ->comment('Obliga a cambiar la contraseña temporal en el primer inicio de sesión (HU-080)');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['requires_password_change']);
        });
    }
};
