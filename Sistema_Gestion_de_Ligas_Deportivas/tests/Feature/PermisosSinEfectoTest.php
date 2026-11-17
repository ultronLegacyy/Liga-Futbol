<?php

namespace Tests\Feature;

use App\Services\PermisosRolService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

// HU-083 (revisión, obligatorio 1): la lista de permisos «sin efecto por ahora» coincide con los que ningún código revisa
class PermisosSinEfectoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_lista_de_permisos_sin_efecto_esta_al_dia(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $codigo = collect(['app', 'routes', 'resources'])
            ->flatMap(fn (string $carpeta) => File::allFiles(base_path($carpeta)))
            // La propia lista no cuenta como código que revisa el permiso
            ->reject(fn ($archivo) => str_ends_with($archivo->getPathname(), 'PermisosRolService.php'))
            ->map(fn ($archivo) => $archivo->getContents())
            ->implode("\n");

        $sinUso = Permission::query()->pluck('name')
            ->reject(fn (string $permiso) => preg_match('/(^|[^a-z_.])'.preg_quote($permiso, '/').'([^a-z_.]|$)/', $codigo) === 1)
            ->sort()->values()->all();

        $lista = collect(PermisosRolService::PERMISOS_SIN_EFECTO)->sort()->values()->all();

        $this->assertSame($sinUso, $lista, 'Actualiza PermisosRolService::PERMISOS_SIN_EFECTO: un permiso empezó a revisarse o dejó de hacerlo.');
    }
}
