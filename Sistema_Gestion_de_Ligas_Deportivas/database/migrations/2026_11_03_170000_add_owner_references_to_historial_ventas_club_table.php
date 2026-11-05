<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('historial_ventas_club', function (Blueprint $table): void {
            $table->foreignId('propietario_anterior_usuario_id')
                ->nullable()
                ->after('club_id')
                ->constrained('usuarios')
                ->nullOnDelete();
            $table->foreignId('propietario_nuevo_usuario_id')
                ->nullable()
                ->after('propietario_anterior_usuario_id')
                ->constrained('usuarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('historial_ventas_club', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('propietario_nuevo_usuario_id');
            $table->dropConstrainedForeignId('propietario_anterior_usuario_id');
        });
    }
};
