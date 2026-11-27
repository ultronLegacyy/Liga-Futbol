<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Models\User;
use App\Services\ReglasTemporadaService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\TestCase;

// HU-042 (SCRUM-54, RF-09.2): los empates en puntos se resuelven con los criterios configurados de la temporada
class DesempatesConfigurablesTest extends TestCase
{
    use CreaGrupoConResultados;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * A y B terminan con 3 puntos: A tiene más goles a favor (4 contra 1) y B mejor diferencia (0 contra -1).
     *
     * @return array{0: Grupo, 1: array<string, Equipo>}
     */
    private function empateEntreAyB(array $desempates): array
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B', 'C', 'D'], desempates: $desempates);
        $this->resultado($grupo, 1, $e['A'], 4, 3, $e['C']);
        $this->resultado($grupo, 1, $e['B'], 1, 0, $e['D']);
        $this->resultado($grupo, 2, $e['A'], 0, 2, $e['D']);
        $this->resultado($grupo, 2, $e['B'], 0, 1, $e['C']);

        return [$grupo, $e];
    }

    // CA1: con dos equipos empatados en puntos se aplican en orden los criterios configurados
    public function test_se_aplican_en_orden_los_criterios_configurados(): void
    {
        [$grupo, $e] = $this->empateEntreAyB(['goles_favor', 'diferencia_goles']);
        $this->assertLessThan($this->posicion($e['B']), $this->posicion($e['A']), 'Con goles a favor primero, A va arriba');

        [$otro, $o] = $this->empateEntreAyB(['diferencia_goles', 'goles_favor']);
        $this->assertLessThan($this->posicion($o['A']), $this->posicion($o['B']), 'Con diferencia de goles primero, B va arriba');
    }

    // CA2: cambiar el orden en una nueva versión de las reglas (HU-006) recalcula la tabla, sin cambiar código ni capturar nada
    public function test_cambiar_el_orden_recalcula_la_tabla_sin_tocar_codigo(): void
    {
        [$grupo, $e] = $this->empateEntreAyB(['goles_favor', 'diferencia_goles']);
        $this->assertLessThan($this->posicion($e['B']), $this->posicion($e['A']));

        $this->actingAs($this->usuario('Administrador General'));
        app(ReglasTemporadaService::class)->guardar($grupo->temporada, [
            'cuotas' => ['inscripcion' => 1000],
            'fechas_limite' => ['registro' => '2026-12-01'],
            'clasificacion' => ['puntos_victoria' => 3, 'puntos_empate' => 1, 'puntos_derrota' => 0],
            'desempates' => ['diferencia_goles', 'goles_favor'],
            'ascenso_descenso' => ['equipos_ascienden' => 1, 'equipos_descienden' => 1],
        ], 'La asamblea cambió el orden de desempate');

        $this->assertLessThan($this->posicion($e['A']), $this->posicion($e['B']));
        $this->assertSame([2], TablaPosicion::query()->where('grupo_id', $grupo->id)->distinct()->pluck('regla_version')->all());
    }

    // Una temporada sin tabla calculada no gana renglones por guardar reglas
    public function test_guardar_reglas_no_crea_tablas_donde_no_hay(): void
    {
        [$grupo] = $this->grupoConEquipos(['A', 'B']);

        $this->actingAs($this->usuario('Administrador General'));
        app(ReglasTemporadaService::class)->guardar($grupo->temporada, [
            'cuotas' => ['inscripcion' => 1000],
            'fechas_limite' => ['registro' => '2026-12-01'],
            'clasificacion' => ['puntos_victoria' => 3, 'puntos_empate' => 1, 'puntos_derrota' => 0],
            'desempates' => ['goles_favor'],
            'ascenso_descenso' => ['equipos_ascienden' => 1, 'equipos_descienden' => 1],
        ], 'Reglas iniciales');

        $this->assertSame(0, TablaPosicion::query()->where('grupo_id', $grupo->id)->count());
    }

    // La pantalla de la tabla dice en qué orden se desempata
    public function test_la_tabla_muestra_el_orden_de_desempate(): void
    {
        [$grupo] = $this->empateEntreAyB(['goles_favor']);

        $this->actingAs($this->usuario('Secretario'))
            ->get(route('grupos.tabla', $grupo))
            ->assertOk()
            ->assertSeeInOrder(['se desempata por', '1. Goles a favor', '2. Diferencia de goles', 'comparten la posición']);
    }

    private function posicion(Equipo $equipo): int
    {
        return (int) TablaPosicion::query()->where('equipo_id', $equipo->id)->value('posicion');
    }

    private function usuario(string $rol): User
    {
        $usuario = User::create([
            'name' => 'Usuario '.$rol,
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    }
}
