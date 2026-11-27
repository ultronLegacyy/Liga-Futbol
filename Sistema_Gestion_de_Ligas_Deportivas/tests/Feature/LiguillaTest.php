<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\FaseTorneo;
use App\Models\Grupo;
use App\Models\LlaveLiguilla;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\TestCase;

// HU-055 (SCRUM-85, RF-11.1): clasificar a la liguilla y generar las llaves desde la tabla final
class LiguillaTest extends TestCase
{
    use CreaGrupoConResultados;
    use DatabaseTransactions;

    private Grupo $grupo;

    /** @var array<string, \App\Models\Equipo> */
    private array $equipos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        // Fase regular completa de «Todos contra todos»: el de número menor gana 1-0, así la tabla queda E1, E2, ..., E8
        [$this->grupo, $this->equipos] = $this->grupoConEquipos(['E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7', 'E8']);
        $this->clasifican(8);
        $this->jugarRolCompleto($this->grupo, array_values($this->equipos));
    }

    // CA1: con 8 clasificados los cruces son 1° vs 8°, 2° vs 7°, 3° vs 6° y 4° vs 5°, en la fase Liguilla de la temporada
    public function test_genera_los_cruces_segun_el_formato_configurado(): void
    {
        $this->actingAs($this->usuario('Presidente'))
            ->post(route('grupos.liguilla.store', $this->grupo))
            ->assertRedirect(route('grupos.liguilla', $this->grupo))
            ->assertSessionHas('success', 'Se generaron 4 llave(s) de liguilla.');

        $this->assertSame([
            [1, 'E1', 1, 'E8', 8],
            [2, 'E2', 2, 'E7', 7],
            [3, 'E3', 3, 'E6', 6],
            [4, 'E4', 4, 'E5', 5],
        ], $this->llaves());

        $fase = FaseTorneo::query()->where('temporada_id', $this->grupo->temporada_id)->sole();
        $this->assertSame('Liguilla', $fase->tipo);
        $this->assertSame(4, $fase->llaves()->count());
        $this->assertTrue(AuditoriaLog::query()->where('tabla_afectada', 'llaves_liguilla')->where('motivo', 'like', 'Generación de las llaves de liguilla%')->exists());
    }

    // CA1: si clasifican 4, solo entran los 4 primeros: 1° vs 4° y 2° vs 3°
    public function test_respeta_cuantos_equipos_clasifican(): void
    {
        $this->clasifican(4);

        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.liguilla.store', $this->grupo));

        $this->assertSame([[1, 'E1', 1, 'E4', 4], [2, 'E2', 2, 'E3', 3]], $this->llaves());
    }

    // CA2 (RNF-36): la llave conserva la versión de las reglas y los criterios usados, aunque cambien después
    public function test_las_llaves_conservan_las_reglas_usadas(): void
    {
        $presidente = $this->usuario('Presidente');
        $this->actingAs($presidente)->post(route('grupos.liguilla.store', $this->grupo));

        $this->reglas($this->grupo->temporada, [2, 1, 0], ['goles_favor'], version: 2);
        $this->clasifican(4);

        $llave = LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->where('orden', 1)->sole();
        $this->assertSame(1, $llave->regla_version);
        $this->assertSame(1, $llave->regla?->version);
        $this->assertSame(8, $llave->criterios_clasificacion['clasifican_liguilla'] ?? null);
        $this->assertSame($presidente->id, $llave->generada_por_id);

        $this->actingAs($presidente)
            ->get(route('grupos.liguilla', $this->grupo))
            ->assertOk()
            ->assertSee('versión 1 de las reglas')
            ->assertSee('8 equipos clasificados')
            ->assertSeeInOrder(['E1', '(1°)', 'E8', '(8°)']);
    }

    // Antes de generar se ve la vista previa; no se guarda nada hasta confirmar
    public function test_muestra_la_vista_previa_sin_guardar(): void
    {
        $this->actingAs($this->usuario('Presidente'))
            ->get(route('grupos.liguilla', $this->grupo))
            ->assertOk()
            ->assertSee('Vista previa')
            ->assertSee('Generar llaves')
            ->assertSeeInOrder(['E1', 'E8', 'E2', 'E7']);

        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->count());
    }

    // Sin terminar la fase regular, con un número impar o sin configurar los clasificados, no se generan
    public function test_no_genera_si_falta_algo(): void
    {
        $presidente = $this->usuario('Presidente');
        $this->resultado($this->grupo, 2, $this->equipos['E1'], 0, 0, $this->equipos['E2'], validar: false);

        $this->actingAs($presidente)
            ->get(route('grupos.liguilla', $this->grupo))
            ->assertSee('faltan 1 partido(s) con resultado oficial')
            ->assertDontSee('Generar llaves');
        $this->actingAs($presidente)->post(route('grupos.liguilla.store', $this->grupo))->assertSessionHasErrors('liguilla');

        $this->clasifican(3);
        $this->actingAs($presidente)->get(route('grupos.liguilla', $this->grupo))->assertSee('debe ser par');

        $this->grupo->update(['criterios_clasificacion' => null]);
        $this->actingAs($presidente)->get(route('grupos.liguilla', $this->grupo))->assertSee('cuántos equipos del grupo clasifican');

        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->count());
    }

    // Un partido cancelado (HU-029) no impide terminar la fase regular
    public function test_un_partido_cancelado_no_bloquea_la_liguilla(): void
    {
        $cancelado = $this->resultado($this->grupo, 2, $this->equipos['E1'], 0, 0, $this->equipos['E2'], validar: false);
        $cancelado->partido->forceFill(['estado' => 'cancelado'])->save();

        $this->actingAs($this->usuario('Presidente'))
            ->post(route('grupos.liguilla.store', $this->grupo))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->count());
    }

    // Las llaves de un grupo se generan una sola vez
    public function test_no_se_generan_dos_veces(): void
    {
        $presidente = $this->usuario('Presidente');
        $this->actingAs($presidente)->post(route('grupos.liguilla.store', $this->grupo));

        $this->actingAs($presidente)
            ->post(route('grupos.liguilla.store', $this->grupo))
            ->assertSessionHasErrors(['liguilla' => 'Las llaves de liguilla de este grupo ya se generaron.']);

        $this->assertSame(4, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->count());
    }

    // Quien solo consulta el grupo ve la pantalla sin el botón y no puede generar; en temporada cerrada nadie genera
    public function test_permisos_y_temporada_cerrada(): void
    {
        $secretario = $this->usuario('Secretario');
        $this->actingAs($secretario)->get(route('grupos.liguilla', $this->grupo))->assertOk()->assertDontSee('Generar llaves');
        $this->actingAs($secretario)->post(route('grupos.liguilla.store', $this->grupo))->assertForbidden();
        $this->actingAs($this->usuario('Tesorero'))->get(route('grupos.liguilla', $this->grupo))->assertForbidden();

        $this->grupo->temporada->update(['estado' => 'cerrada']);
        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.liguilla.store', $this->grupo))->assertForbidden();

        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->count());
    }

    // Revisión, obligatorio 1: con el rol incompleto no se puede generar, aunque lo jugado ya esté validado
    public function test_con_el_rol_incompleto_no_se_genera(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B', 'C', 'D']);
        $grupo->update(['criterios_clasificacion' => ['clasifican_liguilla' => 2, 'clasifican_consolacion' => 0]]);
        $this->resultado($grupo, 1, $e['A'], 1, 0, $e['B']);
        $this->resultado($grupo, 1, $e['C'], 1, 0, $e['D']);

        $this->actingAs($this->usuario('Presidente'))
            ->get(route('grupos.liguilla', $grupo))
            ->assertSee('El rol de «Todos contra todos» no está completo: faltan 4 enfrentamiento(s)')
            ->assertDontSee('Generar llaves');
        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.liguilla.store', $grupo))->assertSessionHasErrors('liguilla');

        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $grupo->id)->count());
    }

    // Revisión, obligatorio 1: con dos vueltas, la segunda a medias también bloquea; completa, ya se genera
    public function test_dos_vueltas_a_medias_bloquean(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B', 'C', 'D']);
        $grupo->update(['criterios_clasificacion' => ['clasifican_liguilla' => 2, 'clasifican_consolacion' => 0]]);
        $equipos = array_values($e);
        $this->jugarRolCompleto($grupo, $equipos);
        $vuelta = $this->resultado($grupo, 10, $e['B'], 0, 0, $e['A'], validar: false);

        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.liguilla.store', $grupo))->assertSessionHasErrors('liguilla');

        app(\App\Services\ValidacionResultadoService::class)->validar($vuelta, $this->validador());
        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.liguilla.store', $grupo))->assertSessionHasNoErrors();
        $this->assertSame(1, LlaveLiguilla::query()->where('grupo_id', $grupo->id)->count());
    }

    // Revisión, obligatorio 2: dos grupos de la misma temporada cuelgan sus llaves de una sola fase «Liguilla»
    public function test_dos_grupos_de_la_temporada_comparten_la_fase(): void
    {
        $otro = \App\Models\Grupo::factory()->create(['categoria_id' => $this->grupo->categoria_id]);
        $equipos = [];
        foreach (['F1', 'F2'] as $nombre) {
            $equipos[] = \App\Models\Equipo::create(['club_id' => \App\Models\Club::create(['nombre' => 'Club '.$nombre.' '.$otro->id])->id, 'grupo_id' => $otro->id, 'nombre' => $nombre]);
        }
        $otro->update(['criterios_clasificacion' => ['clasifican_liguilla' => 2, 'clasifican_consolacion' => 0]]);
        $this->jugarRolCompleto($otro, $equipos);

        $presidente = $this->usuario('Presidente');
        $this->actingAs($presidente)->post(route('grupos.liguilla.store', $this->grupo))->assertSessionHasNoErrors();
        $this->actingAs($presidente)->post(route('grupos.liguilla.store', $otro))->assertSessionHasNoErrors();

        $this->assertSame(1, FaseTorneo::query()->where('temporada_id', $this->grupo->temporada_id)->where('tipo', 'Liguilla')->count());
        $this->assertSame(5, FaseTorneo::query()->where('temporada_id', $this->grupo->temporada_id)->sole()->llaves()->count());
    }

    // Revisión, obligatorio 3: si el empate cruza el corte, avisa con los equipos y no genera
    public function test_un_empate_en_el_corte_se_avisa(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B', 'C', 'D', 'E']);
        $grupo->update(['criterios_clasificacion' => ['clasifican_liguilla' => 4, 'clasifican_consolacion' => 0]]);
        $this->jugarRolCompleto($grupo, array_values($e), empates: true);

        $this->actingAs($this->usuario('Presidente'))
            ->get(route('grupos.liguilla', $grupo))
            ->assertSee('Empate en el corte de la liguilla: A, B, C, D, E comparten el 1.° lugar')
            ->assertDontSee('Generar llaves');
        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.liguilla.store', $grupo))->assertSessionHasErrors('liguilla');
        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $grupo->id)->count());
    }

    // Revisión, obligatorio 3: un empate dentro de los clasificados también se avisa (no se decide quién es local)
    public function test_un_empate_dentro_de_los_clasificados_se_avisa(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B', 'C', 'D']);
        $grupo->update(['criterios_clasificacion' => ['clasifican_liguilla' => 4, 'clasifican_consolacion' => 0]]);
        $this->jugarRolCompleto($grupo, array_values($e), empates: true);

        $this->actingAs($this->usuario('Presidente'))
            ->get(route('grupos.liguilla', $grupo))
            ->assertSee('Empate dentro de los clasificados: A, B, C, D comparten el 1.° lugar');
        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $grupo->id)->count());
    }

    // Revisión, obligatorio 4: antes de clasificar se recalcula la tabla con las reglas vigentes
    public function test_clasifica_con_las_reglas_vigentes(): void
    {
        $this->reglas($this->grupo->temporada, [2, 1, 0], ['goles_favor'], version: 2);

        $this->actingAs($this->usuario('Presidente'))
            ->get(route('grupos.liguilla', $this->grupo))
            ->assertSee('versión 2 de las reglas');
        $this->actingAs($this->usuario('Presidente'))->post(route('grupos.liguilla.store', $this->grupo))->assertSessionHasNoErrors();

        $this->assertSame([2], LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->distinct()->pluck('regla_version')->all());
        $this->assertSame([2], \App\Models\TablaPosicion::query()->where('grupo_id', $this->grupo->id)->distinct()->pluck('regla_version')->all());
        $this->assertSame(14, \App\Models\TablaPosicion::query()->where('equipo_id', $this->equipos['E1']->id)->value('puntos'));
    }

    // Recomendados: menos equipos que lugares, 403 con mensaje de la liguilla y grupo inexistente
    public function test_menos_equipos_que_lugares_mensaje_403_y_404(): void
    {
        $this->clasifican(10);
        $this->actingAs($this->usuario('Presidente'))
            ->get(route('grupos.liguilla', $this->grupo))
            ->assertOk()
            ->assertSee('Clasifican 10 equipos, pero la tabla del grupo tiene 8.');

        foreach (['Secretario', 'Programador de Partidos', 'Delegado del Club', 'Tribunal de Penas'] as $rol) {
            $this->actingAs($this->usuario($rol))
                ->post(route('grupos.liguilla.store', $this->grupo))
                ->assertForbidden()
                ->assertSee('Solo el Presidente o el Administrador General pueden generar las llaves de liguilla.');
        }

        $this->actingAs($this->usuario('Presidente'))->get('/grupos/999999/liguilla')->assertNotFound();
    }

    private function clasifican(int $equipos): void
    {
        $this->grupo->update(['criterios_clasificacion' => ['clasifican_liguilla' => $equipos, 'clasifican_consolacion' => 0]]);
    }

    /**
     * @return list<array{0: int, 1: string, 2: int, 3: string, 4: int}>  orden, local, su lugar, visitante, su lugar
     */
    private function llaves(): array
    {
        return LlaveLiguilla::query()
            ->where('grupo_id', $this->grupo->id)
            ->with('local:id,nombre', 'visitante:id,nombre')
            ->orderBy('orden')
            ->get()
            ->map(fn (LlaveLiguilla $llave) => [$llave->orden, (string) $llave->local?->nombre, $llave->lugar_local, (string) $llave->visitante?->nombre, $llave->lugar_visitante])
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
