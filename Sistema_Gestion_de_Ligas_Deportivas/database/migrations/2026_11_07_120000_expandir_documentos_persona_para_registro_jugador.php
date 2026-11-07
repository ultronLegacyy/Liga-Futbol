<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE documentos_persona MODIFY tipo ENUM('INE', 'CURP', 'Foto', 'Comprobante') NOT NULL");

        Schema::table('documentos_persona', function (Blueprint $table): void {
            $table->string('disco', 32)->default('private')->after('ruta_storage');
            $table->string('mime_type', 100)->default('application/octet-stream')->after('disco');
            $table->unsignedBigInteger('tamano_bytes')->default(0)->after('mime_type');
        });
    }

    public function down(): void
    {
        DB::table('documentos_persona')->where('tipo', 'Comprobante')->delete();

        Schema::table('documentos_persona', function (Blueprint $table): void {
            $table->dropColumn(['disco', 'mime_type', 'tamano_bytes']);
        });

        DB::statement("ALTER TABLE documentos_persona MODIFY tipo ENUM('INE', 'CURP', 'Foto') NOT NULL");
    }
};
