<?php

namespace Tests\Feature;

use App\Models\Grupo;
use App\Models\PropuestaAscensoDescenso;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\TestCase;

// HU-057 (SCRUM-87, RF-11.3): propuestas de ascenso y descenso versionadas con las reglas usadas
class PropuestaAscensoDescensoTest extends TestCase
{
    use CreaGrupoConResultados;
    use DatabaseTransactions;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        // Reglas versión 1: asciende 1 y desciende 1. Gana 1-0 el que va antes: la tabla final queda A, B, C, D
        [$this->grupo, $e] = $this->grupoConEquipos(['A', 'B', 'C', 'D']);
        $equipos = array_values($e);
        foreach ($equipos as $i => $local) {
            foreach (array_slice($equipos, $i + 1) as $visitante) {
                $this->resultado($this->grupo, $i + 1, $local, 1, 0, $visitante);
            }
        }
    }

    // CA1: la propuesta sale de las reglas configuradas (HU-006) y de la tabla final
    public function test_genera_la_propuesta_con_las_reglas_configuradas(): void
    {
        $this->actingAs($this->usuario('Presidente'))
            ->post(route('grupos.ascenso-descenso.store', $this->grupo))
            ->assertRedirect(route('grupos.ascenso-descenso', $this->grupo))
            ->assertSessionHas('success', 'Se guardó la versión 1 de la propuesta de ascenso y descenso.');

        $this->assertSame([['A', 'asciende', 1], ['D', 'desciende', 4]], $this->propuesta(1));
    }

    // CA1: si las reglas vigentes piden 2 ascensos, la propuesta toma los 2 primeros
    public function test_usa_la_version_vigente_de_las_reglas(): void
    {
        $this->reglasConAscensos(2, 1, version: 2);

        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.ascenso-descenso.store', $this->grupo));

        $this->assertSame([['A', 'asciende', 1], ['B', 'asciende', 2], ['D', 'desciende', 4]], $this->propuesta(1));
        $this->assertSame([2], PropuestaAscensoDescenso::query()->where('grupo_id', $this->grupo->id)->distinct()->pluck('regla_version')->all());
    }

    // CA2: un cambio posterior de las reglas no afecta la versión ya guardada; una propuesta nueva es otra versión
    public function test_un_cambio_de_reglas_no_afecta_la_version_guardada(): void
    {
        $presidente = $this->usuario('Presidente');
        $this->actingAs($presidente)->post(route('grupos.ascenso-descenso.store', $this->grupo));

        $this->reglasConAscensos(2, 2, version: 2);

        $guardada = PropuestaAscensoDescenso::query()->where('grupo_id', $this->grupo->id)->where('version', 1)->get();
        $this->assertSame([1], $guardada->pluck('regla_version')->unique()->values()->all());
        $this->assertSame(['equipos_ascienden' => 1, 'equipos_descienden' => 1], $guardada->first()->reglas_ascenso_descenso);
        $this->assertSame([['A', 'asciende', 1], ['D', 'desciende', 4]], $this->propuesta(1));

        $this->actingAs($presidente)->post(route('grupos.ascenso-descenso.store', $this->grupo));
        $this->assertSame([['A', 'asciende', 1], ['B', 'asciende', 2], ['C', 'desciende', 3], ['D', 'desciende', 4]], $this->propuesta(2));

        $this->actingAs($presidente)
            ->get(route('grupos.ascenso-descenso', $this->grupo))
            ->assertOk()
            ->assertSeeInOrder(['Versión 2', 'reglas versión 2', 'Versión 1', 'reglas versión 1']);
    }

    // Sin terminar la temporada del grupo, con un empate en el corte o con más movimientos que equipos, no se genera
    public function test_no_genera_si_falta_algo(): void
    {
        $presidente = $this->usuario('Presidente');
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B', 'C']);
        $this->resultado($grupo, 1, $e['A'], 0, 0, $e['B']);
        $this->resultado($grupo, 2, $e['B'], 0, 0, $e['C'], validar: false);

        $this->actingAs($presidente)
            ->get(route('grupos.ascenso-descenso', $grupo))
            ->assertSee('faltan 1 partido(s) con resultado oficial')
            ->assertDontSee('Guardar propuesta');

        [$empatado, $o] = $this->grupoConEquipos(['E', 'F', 'G']);
        $this->resultado($empatado, 1, $o['E'], 0, 0, $o['F']);
        $this->resultado($empatado, 2, $o['F'], 0, 0, $o['G']);
        $this->resultado($empatado, 3, $o['E'], 0, 0, $o['G']);
        $this->actingAs($presidente)->get(route('grupos.ascenso-descenso', $empatado))->assertSee('Empate en el corte de ascenso: E, F, G comparten el 1.° lugar');
        $this->actingAs($presidente)->post(route('grupos.ascenso-descenso.store', $empatado))->assertSessionHasErrors('propuesta');

        $this->reglasConAscensos(3, 2, version: 2);
        $this->actingAs($presidente)->get(route('grupos.ascenso-descenso', $this->grupo))->assertSee('Las reglas piden 3 ascenso(s) y 2 descenso(s), pero el grupo tiene 4 equipo(s).');

        $this->assertSame(0, PropuestaAscensoDescenso::query()->count());
    }

    // Quien consulta el grupo ve la pantalla sin el botón; generan el Presidente y el Administrador; nunca en temporada cerrada
    public function test_permisos_y_temporada_cerrada(): void
    {
        $secretario = $this->usuario('Secretario');
        $this->actingAs($secretario)->get(route('grupos.ascenso-descenso', $this->grupo))->assertOk()->assertSee('Vista previa')->assertDontSee('Guardar propuesta');
        $this->actingAs($secretario)
            ->post(route('grupos.ascenso-descenso.store', $this->grupo))
            ->assertForbidden()
            ->assertSee('Solo el Presidente o el Administrador General pueden generar propuestas de ascenso y descenso.');
        $this->actingAs($this->usuario('Tesorero'))->get(route('grupos.ascenso-descenso', $this->grupo))->assertForbidden();

        $this->actingAs($this->usuario('Administrador General'))->post(route('grupos.ascenso-descenso.store', $this->grupo))->assertSessionHasNoErrors();

        $this->grupo->temporada->update(['estado' => 'cerrada']);
        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.ascenso-descenso.store', $this->grupo))->assertForbidden();
        $this->assertSame(1, PropuestaAscensoDescenso::query()->where('grupo_id', $this->grupo->id)->max('version'));
    }

    private function reglasConAscensos(int $ascienden, int $descienden, int $version): void
    {
        \App\Models\ReglaTemporada::create([
            'temporada_id' => $this->grupo->temporada_id,
            'version' => $version,
            'reglas' => [
                'cuotas' => ['inscripcion' => 1000],
                'fechas_limite' => ['registro' => '2026-12-01'],
                'clasificacion' => ['puntos_victoria' => 3, 'puntos_empate' => 1, 'puntos_derrota' => 0],
                'desempates' => ['diferencia_goles', 'goles_favor'],
                'ascenso_descenso' => ['equipos_ascienden' => $ascienden, 'equipos_descienden' => $descienden],
            ],
            'motivo' => 'Reglas de prueba',
        ]);
    }

    /**
     * @return list<array{0: string, 1: string, 2: int}>  equipo, movimiento, posición
     */
    private function propuesta(int $version): array
    {
        return PropuestaAscensoDescenso::query()
            ->where('grupo_id', $this->grupo->id)
            ->where('version', $version)
            ->with('equipo:id,nombre')
            ->orderBy('posicion')
            ->get()
            ->map(fn (PropuestaAscensoDescenso $fila) => [(string) $fila->equipo?->nombre, $fila->movimiento, $fila->posicion])
            ->all();
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
