<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('jugador_documentos');
    }

    public function down(): void
    {
        Schema::create('jugador_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jugador_id')->constrained('jugadores_equipos')->cascadeOnDelete();
            $table->enum('tipo', ['INE', 'CURP', 'Foto', 'Comprobante']);
            $table->string('disco')->default('private');
            $table->string('ruta_storage');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamano_bytes');
            $table->timestamps();
            $table->unique(['jugador_id', 'tipo']);
        });
    }
};
