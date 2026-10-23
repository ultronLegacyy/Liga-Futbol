<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

// HU-080 (CA1): una base que ya tenía los roles del Sprint 1 queda solo con los 13 de la ERS, sin que las cuentas pierdan su acceso
class ConciliacionDeRolesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_las_cuentas_pasan_al_rol_de_la_ers_con_sus_permisos_y_los_roles_viejos_desaparecen(): void
    {
        // Así quedaba una base sembrada antes de HU-080
        $this->rolViejo('Comision Disciplinaria', ['disciplina.ver', 'disciplina.sancionar']);
        $this->rolViejo('Directivo de Club', ['clubes.ver', 'campos.ver']);
        $this->rolViejo('Arbitro', ['temporadas.ver', 'campos.ver']);
        $this->rolViejo('Consulta Publica', ['temporadas.ver']);

        $disciplina = $this->cuenta('disciplina_conciliacion@liga.com', 'Comision Disciplinaria');
        $directivo = $this->cuenta('directivo_conciliacion@liga.com', 'Directivo de Club');
        $arbitro = $this->cuenta('arbitro_conciliacion@liga.com', 'Arbitro');
        $consulta = $this->cuenta('consulta_conciliacion@liga.com', 'Consulta Publica');

        $this->migracion()->up();

        foreach (['Comision Disciplinaria', 'Directivo de Club', 'Arbitro', 'Consulta Publica'] as $retirado) {
            $this->assertDatabaseMissing('roles', ['name' => $retirado]);
        }
        $this->assertSame(13, Role::count());

        $this->assertSame(['Tribunal de Penas'], $disciplina->fresh()->getRoleNames()->all());
        $this->assertSame(['Presidente del Club'], $directivo->fresh()->getRoleNames()->all());
        $this->assertSame(['Árbitro en Campo'], $arbitro->fresh()->getRoleNames()->all());
        $this->assertSame([], $consulta->fresh()->getRoleNames()->all());

        // Las cuentas conservan lo que podían hacer, y el Delegado del Club recibe lo mismo que el Presidente del Club
        $this->assertTrue($directivo->fresh()->can('clubes.ver'));
        $this->assertTrue($arbitro->fresh()->can('campos.ver'));
        $this->assertTrue($disciplina->fresh()->can('disciplina.sancionar'));
        $this->assertTrue(Role::findByName('Delegado del Club')->hasPermissionTo('clubes.ver'));

        // Cada cambio de rol queda en la auditoría con el motivo de la conciliación
        $asignado = AuditoriaLog::query()
            ->where('tabla_afectada', 'usuarios')
            ->where('registro_id', $arbitro->id)
            ->where('accion', 'rol_asignado')
            ->latest('id')
            ->first();

        $this->assertSame(['roles' => ['Árbitro en Campo']], $asignado->valores_nuevos);
        $this->assertSame('Conciliación del catálogo de roles con la sección 2.3 de la ERS (HU-080)', $asignado->motivo);
    }

    public function test_en_una_base_sin_roles_viejos_no_hace_nada(): void
    {
        $antes = Role::query()->orderBy('name')->pluck('name')->all();

        $this->migracion()->up();

        $this->assertSame($antes, Role::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(13, Role::count());
    }

    private function migracion(): Migration
    {
        return require database_path('migrations/2026_10_23_120000_conciliar_roles_con_la_ers.php');
    }

    /**
     * @param  list<string>  $permisos
     */
    private function rolViejo(string $nombre, array $permisos): void
    {
        Role::create(['name' => $nombre, 'guard_name' => 'web'])->givePermissionTo($permisos);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function cuenta(string $email, string $rol): User
    {
        $usuario = User::create([
            'name' => 'Cuenta '.$rol,
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            // La columna rol solo admite los valores del Sprint 1
            'rol' => $rol === 'Directivo de Club' ? 'Directivo Club' : ($rol === 'Arbitro' ? 'Arbitro' : 'Consultor'),
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }
}
