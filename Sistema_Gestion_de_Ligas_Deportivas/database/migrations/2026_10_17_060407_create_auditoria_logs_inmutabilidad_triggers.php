<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // La auditoría es inmutable (RNF-33): la base rechaza cualquier UPDATE o DELETE sobre sus filas
        // Con binlog activo en producción, crear triggers puede requerir privilegios adicionales al usuario que migra
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER auditoria_logs_impedir_update
            BEFORE UPDATE ON auditoria_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoría es inmutable: no se permite modificar sus registros.'
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER auditoria_logs_impedir_delete
            BEFORE DELETE ON auditoria_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoría es inmutable: no se permite eliminar sus registros.'
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS auditoria_logs_impedir_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS auditoria_logs_impedir_update');
    }
};
