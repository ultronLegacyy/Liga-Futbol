<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-067 (RF-15.4): cada correo que manda el sistema, con fecha, destinatarios y estado. Sin cuerpo (decisión de Daniel, 20/11)
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('registro_correos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40)->index();
            $table->string('asunto', 255);
            $table->json('destinatarios');
            $table->string('estado', 20)->index();
            // Solo la clase del error; el detalle va al log, porque el mensaje de SMTP puede traer el usuario
            $table->string('error', 120)->nullable();
            // Evita mandar dos veces el mismo aviso si la cola lo reintenta o se reenvía
            $table->string('clave', 191)->nullable()->unique();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_correos');
    }
};
