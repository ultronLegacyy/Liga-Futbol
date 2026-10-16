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
        // 1. ligas
        Schema::create('ligas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('logo_url')->nullable();
            $table->enum('estado', ['activa', 'inactiva'])->default('activa');
            $table->timestamps();
        });

        // 2. clubes (creado aqui para permitir FK en usuarios)
        Schema::create('clubes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('logo')->nullable();
            $table->string('colores')->nullable();
            $table->date('fecha_afiliacion')->nullable();
            $table->enum('estado', ['activo', 'inactivo', 'suspendido'])->default('activo');
            $table->timestamps();
        });

        // 3. usuarios
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('password_hash')->nullable();
            $table->enum('rol', [
                'Administrador General',
                'Presidente',
                'Secretario',
                'Tesorero',
                'Comision Arbitral',
                'Tribunal de Penas',
                'Comite Ejecutivo',
                'Directivo Club',
                'Arbitro',
                'Jugador',
                'Capturista',
                'Consultor'
            ])->default('Consultor');
            $table->foreignId('club_id')->nullable()->constrained('clubes')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 4. auditoria_logs
        Schema::create('auditoria_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('accion');
            $table->string('tabla_afectada');
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 5. asambleas
        Schema::create('asambleas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('acta_pdf_url')->nullable();
            $table->text('acuerdos')->nullable();
            $table->timestamps();
        });

        // 6. comunicados_noticias
        Schema::create('comunicados_noticias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liga_id')->constrained('ligas')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('contenido');
            $table->enum('estado', ['Borrador', 'Publicado'])->default('Borrador');
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
        });

        // 7. registro_notificaciones
        Schema::create('registro_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('destinatario_email');
            $table->string('asunto');
            $table->string('tipo_aviso');
            $table->enum('estado', ['Enviado', 'Fallido'])->default('Enviado');
            $table->timestamp('fecha_envio')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registro_notificaciones');
        Schema::dropIfExists('comunicados_noticias');
        Schema::dropIfExists('asambleas');
        Schema::dropIfExists('auditoria_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('clubes');
        Schema::dropIfExists('ligas');
    }
};
