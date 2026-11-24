<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-078 (PA-15): un comunicado de asamblea solo se ve en el portal público cuando se publica de forma expresa
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('comunicados_asamblea', function (Blueprint $table): void {
            $table->boolean('publicado_en_portal')->default(false)->after('contenido');
            $table->foreignId('publicado_por_usuario_id')->nullable()->after('publicado_en_portal')
                ->constrained('usuarios')->restrictOnDelete();
            $table->timestamp('publicado_en')->nullable()->after('publicado_por_usuario_id');
            $table->index(['publicado_en_portal', 'publicado_en']);
        });
    }

    public function down(): void
    {
        Schema::table('comunicados_asamblea', function (Blueprint $table): void {
            $table->dropIndex(['publicado_en_portal', 'publicado_en']);
            $table->dropConstrainedForeignId('publicado_por_usuario_id');
            $table->dropColumn(['publicado_en_portal', 'publicado_en']);
        });
    }
};
