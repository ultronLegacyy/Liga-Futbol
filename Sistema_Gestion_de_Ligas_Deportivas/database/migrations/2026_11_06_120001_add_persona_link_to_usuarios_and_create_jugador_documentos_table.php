<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->foreignId('persona_id')->nullable()->unique()->after('club_id')
                ->constrained('personas')->nullOnDelete();
        });

        Schema::create('jugador_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jugador_id')->constrained('jugadores_equipos')->cascadeOnDelete();
            $table->enum('tipo', ['INE', 'CURP', 'Fotografia', 'Comprobante']);
            $table->string('disco')->default('private');
            $table->string('ruta_storage');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamano_bytes');
            $table->timestamps();
            $table->unique(['jugador_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jugador_documentos');

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropForeign(['persona_id']);
            $table->dropUnique(['persona_id']);
            $table->dropColumn('persona_id');
        });
    }
};
