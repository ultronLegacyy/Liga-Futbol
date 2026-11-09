<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->foreignId('persona_id')->nullable()->unique()->after('club_id')
                ->constrained('personas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('usuarios')->whereNotNull('persona_id')->exists()) {
            throw new RuntimeException('No se puede revertir el vínculo persona-cuenta mientras existan cuentas vinculadas.');
        }

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropForeign(['persona_id']);
            $table->dropUnique(['persona_id']);
            $table->dropColumn('persona_id');
        });
    }
};
