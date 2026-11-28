<?php

namespace Tests\Feature;

use App\Models\CedulaArbitral;
use App\Models\ComunicadoAsamblea;
use App\Models\Sancion;
use App\Models\TablaPosicion;
use App\Models\Temporada;
use App\Models\User;
use Database\Seeders\LigaDemoSeeder;
use Database\Seeders\PortalProgramacionDemoSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// HAB-S7 · S7-T07 (SCRUM-240): liga de demostración para revisar el portal con datos que parecen reales
class LigaDemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_llena_resultados_tablas_goleo_castigados_y_comunicados_y_se_puede_repetir(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        // Usuarios de DatabaseSeeder que la demostración usa para validar y publicar
        foreach (['admin', 'secretario', 'tribunal'] as $usuario) {
            User::query()->firstOrCreate(
                ['email' => $usuario.'@ligafutbol.com'],
                ['name' => ucfirst($usuario), 'password' => Hash::make('secreto123'), 'estatus' => 'activo', 'rol' => 'Consultor'],
            );
        }
        $this->seed(PortalProgramacionDemoSeeder::class);

        $this->seed(LigaDemoSeeder::class);
        $this->seed(LigaDemoSeeder::class);

        $temporada = Temporada::query()->where('nombre', LigaDemoSeeder::NOMBRE)->sole();
        // La demostración anterior («Local 1 vs. Visitante 1») se retira
        $this->assertFalse(Temporada::query()->where('nombre', PortalProgramacionDemoSeeder::NOMBRE)->exists());
        $this->assertSame(36, CedulaArbitral::query()->oficiales()->whereHas('partido.jornada.grupo', fn ($grupo) => $grupo->where('temporada_id', $temporada->id))->count());
        $this->assertSame(18, TablaPosicion::query()->whereHas('grupo', fn ($grupo) => $grupo->where('temporada_id', $temporada->id))->count());
        $this->assertGreaterThanOrEqual(3, Sancion::query()->where('publicada_en_boletin', true)->count());
        $this->assertSame(2, ComunicadoAsamblea::query()->publicadosEnPortal()->count());

        $this->get(route('portal.inicio'))->assertOk()->assertSee('Halcones Tarímbaro')->assertSee('Grupo A');
        $this->get(route('portal.resultados'))->assertOk()->assertSee('Tabla de posiciones')->assertSee('Goleo');
        $this->get(route('portal.castigados'))->assertOk()->assertSee('Liguilla de Primera Fuerza')->assertSee('Suspensión de');
        $this->get(route('portal.programacion'))->assertOk()->assertDontSee('Local 1');
    }
}
