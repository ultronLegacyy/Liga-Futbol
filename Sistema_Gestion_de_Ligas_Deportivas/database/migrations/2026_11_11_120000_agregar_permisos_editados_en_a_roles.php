<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-083 CA4: marca los roles cuyos permisos editó el Administrador General,
// para que la carga inicial (RoleAndPermissionSeeder) ya no los regrese a su valor original
return new class () extends Migration {
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table): void {
            $table->timestamp('permisos_editados_en')->nullable()->after('guard_name');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles', 'roles'), function (Blueprint $table): void {
            $table->dropColumn('permisos_editados_en');
        });
    }
};
