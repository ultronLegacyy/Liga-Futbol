<?php

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// HU-080 (CA1): los 4 roles del Sprint 1 que no están en la sección 2.3 de la ERS pasan a sus equivalentes.
// Las cuentas conservan su acceso; «Consulta Publica» era el Público General, que no tiene cuenta, y se retira.
// En una base vacía no hace nada: el seeder ya crea solo los 13 roles de la ERS.
return new class () extends Migration {
    /**
     * Rol viejo => rol que reciben sus cuentas y roles que heredan sus permisos.
     *
     * @var array<string, array{cuentas: string|null, permisos: list<string>}>
     */
    private const EQUIVALENCIAS = [
        'Comision Disciplinaria' => ['cuentas' => 'Tribunal de Penas', 'permisos' => ['Tribunal de Penas']],
        'Directivo de Club' => ['cuentas' => 'Presidente del Club', 'permisos' => ['Presidente del Club', 'Delegado del Club']],
        'Arbitro' => ['cuentas' => 'Árbitro en Campo', 'permisos' => ['Árbitro en Campo']],
        'Consulta Publica' => ['cuentas' => null, 'permisos' => []],
    ];

    public function up(): void
    {
        $viejos = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys(self::EQUIVALENCIAS))
            ->get();

        if ($viejos->isEmpty()) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Cada asignación y retiro queda en la auditoría con este motivo, y todo se guarda junto o nada (RNF-22, RNF-33)
        app(AuditService::class)->conMotivo('Conciliación del catálogo de roles con la sección 2.3 de la ERS (HU-080)', function () use ($viejos): void {
            foreach ($viejos as $viejo) {
                $equivalencia = self::EQUIVALENCIAS[$viejo->name];

                foreach ($equivalencia['permisos'] as $nombre) {
                    Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web'])
                        ->givePermissionTo($viejo->permissions);
                }

                foreach (User::role($viejo->name)->get() as $cuenta) {
                    if ($equivalencia['cuentas'] !== null) {
                        $cuenta->assignRole($equivalencia['cuentas']);
                    }

                    $cuenta->removeRole($viejo);
                }

                $viejo->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // Sin vuelta atrás: los roles viejos no se recrean, para no volver a duplicar el catálogo
    public function down(): void
    {
    }
};
