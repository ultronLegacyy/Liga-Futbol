<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE documentos_persona MODIFY tipo ENUM('INE', 'CURP', 'Foto', 'Comprobante') NOT NULL");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('documentos_persona', function (Blueprint $table): void {
                $table->string('tipo')->change();
            });
        }

        Schema::table('documentos_persona', function (Blueprint $table): void {
            $table->string('disco', 32)->default('private')->after('ruta_storage');
            $table->string('mime_type', 100)->default('application/octet-stream')->after('disco');
            $table->unsignedBigInteger('tamano_bytes')->default(0)->after('mime_type');
        });
    }

    public function down(): void
    {
        $documentosNoReversibles = DB::table('documentos_persona')
            ->where(function ($query): void {
                $query->where('tipo', 'Comprobante')
                    ->orWhere('disco', '!=', 'private')
                    ->orWhere('mime_type', '!=', 'application/octet-stream')
                    ->orWhere('tamano_bytes', '>', 0);
            })
            ->exists();

        if ($documentosNoReversibles) {
            throw new RuntimeException('No se puede revertir el esquema documental mientras existan comprobantes o metadatos de archivo.');
        }

        Schema::table('documentos_persona', function (Blueprint $table): void {
            $table->dropColumn(['disco', 'mime_type', 'tamano_bytes']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE documentos_persona MODIFY tipo ENUM('INE', 'CURP', 'Foto') NOT NULL");
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('documentos_persona', function (Blueprint $table): void {
                $table->enum('tipo', ['INE', 'CURP', 'Foto'])->change();
            });
        }
    }
};
