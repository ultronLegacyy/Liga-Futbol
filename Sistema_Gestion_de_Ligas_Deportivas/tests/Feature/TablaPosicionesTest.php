<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Models\User;
use App\Services\TablaPosicionesService;
use App\Services\ValidacionResultadoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\TestCase;

// HU-041 (SCRUM-53, RF-09.1): tabla de posiciones calculada al validar cada resultado
class TablaPosicionesTest extends TestCase
{
    use CreaGrupoConResultados;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * CA1 y CA2 (RNF-01): 3 categorías con 2 jornadas cada una; la tabla calculada coincide con el cálculo a mano en todas las celdas.
     * Columnas esperadas: posición, PJ, PG, PE, PP, GF, GC, DG, puntos.
     */
    public function test_la_tabla_coincide_con_el_calculo_manual_en_tres_categorias(): void
    {
        // Categoría 1: 3-1-0, desempate por diferencia y luego goles a favor; un resultado sin validar no cuenta
        [$grupo1, $e] = $this->grupoConEquipos();
        $this->resultado($grupo1, 1, $e['A'], 2, 1, $e['B']);
        $this->resultado($grupo1, 1, $e['C'], 0, 0, $e['D']);
        $this->resultado($grupo1, 2, $e['A'], 1, 1, $e['C']);
        $this->resultado($grupo1, 2, $e['B'], 3, 0, $e['D']);
        $this->resultado($grupo1, 3, $e['D'], 5, 0, $e['A'], validar: false);

        $this->assertTabla($grupo1, [
            'A' => [1, 2, 1, 1, 0, 3, 2, 1, 4],
            'B' => [2, 2, 1, 0, 1, 4, 2, 2, 3],
            'C' => [3, 2, 0, 2, 0, 1, 1, 0, 2],
            'D' => [4, 2, 0, 1, 1, 0, 3, -3, 1],
        ]);

        // Categoría 2: B y C empatan a 3 puntos; el primer desempate configurado es goles a favor
        [$grupo2, $e] = $this->grupoConEquipos(desempates: ['goles_favor', 'diferencia_goles']);
        $this->resultado($grupo2, 1, $e['A'], 3, 2, $e['B']);
        $this->resultado($grupo2, 1, $e['C'], 1, 0, $e['D']);
        $this->resultado($grupo2, 2, $e['B'], 2, 0, $e['C']);
        $this->resultado($grupo2, 2, $e['D'], 1, 1, $e['A']);

        $this->assertTabla($grupo2, [
            'A' => [1, 2, 1, 1, 0, 4, 3, 1, 4],
            'B' => [2, 2, 1, 0, 1, 4, 3, 1, 3],
            'C' => [3, 2, 1, 0, 1, 1, 2, -1, 3],
            'D' => [4, 2, 0, 1, 1, 1, 2, -1, 1],
        ]);

        // Categoría 3: otra temporada con 2-1-0; B y D empatan a 2 puntos y desempata la diferencia de goles
        [$grupo3, $e] = $this->grupoConEquipos(puntos: [2, 1, 0], desempates: ['diferencia_goles']);
        $this->resultado($grupo3, 1, $e['A'], 0, 2, $e['B']);
        $this->resultado($grupo3, 1, $e['C'], 1, 1, $e['D']);
        $this->resultado($grupo3, 2, $e['A'], 2, 2, $e['D']);
        $this->resultado($grupo3, 2, $e['B'], 0, 1, $e['C']);

        $this->assertTabla($grupo3, [
            'C' => [1, 2, 1, 1, 0, 2, 1, 1, 3],
            'B' => [2, 2, 1, 0, 1, 2, 1, 1, 2],
            'D' => [3, 2, 0, 2, 0, 3, 3, 0, 2],
            'A' => [4, 2, 0, 1, 1, 2, 4, -2, 1],
        ]);
    }

    // CA1: antes de validar no hay tabla; al validar se calcula, también con equipos que aún no juegan
    public function test_solo_los_resultados_validados_entran_a_la_tabla(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B', 'C']);
        $cedula = $this->resultado($grupo, 1, $e['A'], 1, 0, $e['B'], validar: false);

        $this->assertSame(0, TablaPosicion::query()->where('grupo_id', $grupo->id)->count());

        app(ValidacionResultadoService::class)->validar($cedula, $this->validador());

        $this->assertTabla($grupo, [
            'A' => [1, 1, 1, 0, 0, 1, 0, 1, 3],
            // C (0 puntos, DG 0) queda arriba de B (0 puntos, DG -1) por la diferencia de goles
            'C' => [2, 0, 0, 0, 0, 0, 0, 0, 0],
            'B' => [3, 1, 0, 0, 1, 0, 1, -1, 0],
        ]);
    }

    // CA1: los puntos salen de la versión vigente de las reglas y la tabla guarda cuál usó
    public function test_usa_la_version_vigente_de_las_reglas(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B']);
        $this->reglas($grupo->temporada, [2, 1, 0], ['diferencia_goles'], version: 2);

        $this->resultado($grupo, 1, $e['A'], 1, 0, $e['B']);

        $fila = TablaPosicion::query()->where('equipo_id', $e['A']->id)->sole();
        $this->assertSame(2, $fila->puntos);
        $this->assertSame(2, $fila->regla_version);
    }

    // Sin reglas de puntos no se puede calcular: la validación se rechaza y el resultado sigue pendiente
    public function test_sin_reglas_de_puntos_no_se_valida(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B'], conReglas: false);
        $cedula = $this->resultado($grupo, 1, $e['A'], 1, 0, $e['B'], validar: false);

        try {
            app(ValidacionResultadoService::class)->validar($cedula, $this->validador());
            $this->fail('Se validó sin reglas de puntos.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('reglas de puntos', $error->getMessage());
        }

        $this->assertNull($cedula->refresh()->validada_en);
    }

    // CA4 (RNF-22): si el cálculo falla a la mitad, no quedan datos parciales y el resultado no queda validado
    public function test_una_falla_a_la_mitad_no_deja_datos_parciales(): void
    {
        [$grupo, $e] = $this->grupoConEquipos();
        $this->resultado($grupo, 1, $e['A'], 2, 0, $e['B']);
        $antes = $this->filas($grupo);
        $cedula = $this->resultado($grupo, 1, $e['C'], 1, 0, $e['D'], validar: false);

        $creados = 0;
        TablaPosicion::creating(function () use (&$creados): void {
            if (++$creados === 3) {
                throw new RuntimeException('Falla simulada a la mitad del cálculo');
            }
        });

        try {
            app(ValidacionResultadoService::class)->validar($cedula, $this->validador());
            $this->fail('La falla no se propagó.');
        } catch (RuntimeException) {
        }

        $this->assertSame($antes, $this->filas($grupo));
        $this->assertNull($cedula->refresh()->validada_en);
    }

    // CA3 (RNF-06): validar un resultado y recalcular un grupo de 10 equipos con 45 partidos oficiales tarda menos de 2 segundos
    public function test_validar_y_recalcular_responde_en_menos_de_dos_segundos(): void
    {
        $nombres = array_map(fn (int $n) => 'Equipo '.str_pad((string) $n, 2, '0', STR_PAD_LEFT), range(1, 10));
        [$grupo, $e] = $this->grupoConEquipos($nombres);
        $equipos = array_values($e);

        $jornada = 1;
        for ($i = 0; $i < 10; $i++) {
            for ($j = $i + 1; $j < 10; $j++) {
                $cedula = $this->resultado($grupo, $jornada, $equipos[$i], ($i + $j) % 4, ($i * $j) % 3, $equipos[$j], validar: false);
                $cedula->forceFill(['validada_en' => now()])->save();
                $jornada = $jornada % 9 + 1;
            }
        }
        $ultimo = $this->resultado($grupo, 10, $equipos[0], 1, 0, $equipos[9], validar: false);

        $inicio = microtime(true);
        app(ValidacionResultadoService::class)->validar($ultimo, $this->validador());
        $segundos = microtime(true) - $inicio;

        $this->assertLessThan(2.0, $segundos);
        $this->assertSame(10, TablaPosicion::query()->where('grupo_id', $grupo->id)->count());
        $this->assertSame(46 * 2, (int) TablaPosicion::query()->where('grupo_id', $grupo->id)->sum('pj'));
    }

    // La pantalla muestra la tabla en orden y la distingue como dato calculado
    public function test_la_pantalla_muestra_la_tabla(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['Leones', 'Tigres']);
        $this->resultado($grupo, 1, $e['Tigres'], 3, 1, $e['Leones']);

        $this->actingAs($this->usuario('Secretario'))
            ->get(route('grupos.tabla', $grupo))
            ->assertOk()
            ->assertSee('Calculado')
            ->assertSee('versión 1 de las reglas')
            ->assertSeeInOrder(['Tigres', 'Leones']);

        $this->actingAs($this->usuario('Secretario'))
            ->get(route('categorias.show', $grupo->categoria))
            ->assertSee('href="'.route('grupos.tabla', $grupo).'"', false);
    }

    // Sin el permiso para consultar grupos no se ve la tabla
    public function test_sin_permiso_no_se_consulta(): void
    {
        [$grupo] = $this->grupoConEquipos(['A', 'B']);

        $this->get(route('grupos.tabla', $grupo))->assertRedirect(route('login'));
        $this->actingAs($this->usuario('Tesorero'))->get(route('grupos.tabla', $grupo))->assertForbidden();
    }

    /**
     * @param  array<string, list<int>>  $esperado  equipo => [posición, PJ, PG, PE, PP, GF, GC, DG, puntos]
     */
    private function assertTabla(Grupo $grupo, array $esperado): void
    {
        $calculada = TablaPosicion::query()
            ->where('tablas_posiciones.grupo_id', $grupo->id)
            ->join('equipos', 'equipos.id', '=', 'tablas_posiciones.equipo_id')
            ->orderBy('posicion')
            ->get(['equipos.nombre', 'posicion', 'pj', 'pg', 'pe', 'pp', 'gf', 'gc', 'dg', 'puntos'])
            ->mapWithKeys(fn ($fila) => [$fila->nombre => [$fila->posicion, $fila->pj, $fila->pg, $fila->pe, $fila->pp, $fila->gf, $fila->gc, $fila->dg, $fila->puntos]])
            ->all();

        $this->assertSame($esperado, $calculada);

        // La tabla guardada es la misma que calcula el servicio en ese momento
        $servicio = app(TablaPosicionesService::class);
        $this->assertSame(
            array_keys($esperado),
            array_column($servicio->calcular($grupo, $servicio->reglaVigente($grupo)), 'nombre')
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filas(Grupo $grupo): array
    {
        return TablaPosicion::query()->where('grupo_id', $grupo->id)->orderBy('equipo_id')
            ->get(['equipo_id', 'posicion', 'pj', 'puntos'])->map->toArray()->all();
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
