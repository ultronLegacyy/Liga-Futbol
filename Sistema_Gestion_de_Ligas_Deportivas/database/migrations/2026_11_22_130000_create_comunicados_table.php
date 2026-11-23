<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-066 (RF-15.3): comunicados por correo a grupos de usuarios; queda quién mandó qué y a qué grupo
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('comunicados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enviado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('asunto', 200);
            $table->text('mensaje');
            // Roles, categoría y clubes elegidos (decisión 5 de Daniel)
            $table->json('filtros');
            $table->unsignedInteger('total_destinatarios');
            // Un solo envío por revisión: un doble clic o «Atrás» y reenviar chocan con este índice
            $table->uuid('token')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicados');
    }
};
