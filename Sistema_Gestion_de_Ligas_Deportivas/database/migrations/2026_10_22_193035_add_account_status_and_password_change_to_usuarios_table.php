<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->boolean('is_active')
                  ->default(true)
                  ->after('club_id')
                  ->comment('Indica si la cuenta está activa (HU-080)');
            
            $table->boolean('requires_password_change')
                  ->default(true)
                  ->after('is_active')
                  ->comment('Obliga a cambiar la contraseña temporal en el primer inicio de sesión (HU-080)');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'requires_password_change']);
        });
    }
};