<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HU-020 (OR-01): constancia de que se mostró el aviso de privacidad al registrar a un
 * jugador y, si es menor, del consentimiento digital de su tutor.
 *
 * Una fila por inscripción. jugador_id usa nullOnDelete por la venta de club (HU-011):
 * la constancia se conserva ligada a la persona aunque se borre la inscripción.
 * El teléfono y el correo del tutor se guardan cifrados (cast encrypted), por eso son text.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('consentimientos_privacidad_jugador', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('jugador_id')->nullable()->unique()->constrained('jugadores_equipos')->nullOnDelete();
            $table->string('version_aviso', 30);
            $table->timestamp('aviso_mostrado_en');
            $table->boolean('requiere_tutor')->default(false);
            $table->string('motivo_tutor', 30)->nullable();
            $table->string('tutor_nombre_completo', 200)->nullable();
            $table->string('tutor_parentesco', 40)->nullable();
            $table->text('tutor_telefono')->nullable();
            $table->text('tutor_correo')->nullable();
            $table->timestamp('tutor_consintio_en')->nullable();
            // Nombre explícito: el automático supera los 64 caracteres que admite MySQL/MariaDB.
            $table->foreignId('registrado_por_usuario_id')
                ->constrained('usuarios', 'id', 'consent_priv_jugador_registrado_por_fk')
                ->restrictOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['persona_id', 'created_at']);
        });
    }

    /**
     * Se niega a revertir si hay constancias registradas: son evidencia legal del consentimiento.
     */
    public function down(): void
    {
        if (Schema::hasTable('consentimientos_privacidad_jugador') && DB::table('consentimientos_privacidad_jugador')->exists()) {
            throw new RuntimeException('No se puede revertir SCRUM-35 mientras existan consentimientos registrados.');
        }

        Schema::dropIfExists('consentimientos_privacidad_jugador');
    }
};
