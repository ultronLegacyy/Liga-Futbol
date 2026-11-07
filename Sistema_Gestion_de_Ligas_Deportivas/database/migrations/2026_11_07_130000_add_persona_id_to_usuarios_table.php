<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->foreignId('persona_id')->nullable()->unique('usuarios_persona_id_unique')->after('club_id')
                ->constrained('personas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropForeign(['persona_id']);
            $table->dropUnique('usuarios_persona_id_unique');
            $table->dropColumn('persona_id');
        });
    }
};
