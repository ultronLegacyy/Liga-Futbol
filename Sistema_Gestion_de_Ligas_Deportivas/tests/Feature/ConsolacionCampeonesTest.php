<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CedulaArbitral;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\EventoPartido;
use App\Models\FaseTorneo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\LlaveLiguilla;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\User;
use App\Services\EstadisticasGrupoService;
use App\Services\LiguillaService;
use App\Services\ValidacionResultadoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\TestCase;

// HU-060 (SCRUM-90, RF-11.6): liguilla de consolación, Campeón de Campeones y campeón de goleo de cada categoría
class ConsolacionCampeonesTest extends TestCase
{
    use CreaGrupoConResultados;
    use DatabaseTransactions;

    private Grupo $grupo;

    /** @var array<string, Equipo> */
    private array $equipos;

    private User $presidente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->presidente = $this->usuario('Presidente');
    }

    // CA1: los lugares 9 a 16 (8 a la liguilla y 8 a la consolación) juegan 9° vs 16°... y la final da el campeón alterno
    public function test_la_consolacion_de_los_lugares_9_a_16_da_un_campeon_alterno(): void
    {
        $this->grupoDe(16);

        $this->generar('consolacion')->assertSessionHas('success', 'Se generaron 4 llave(s) de liguilla de consolación.');
        $this->assertSame([[1, 'E9', 9, 'E16', 16], [2, 'E10', 10, 'E15', 15], [3, 'E11', 11, 'E14', 14], [4, 'E12', 12, 'E13', 13]], $this->cruces(FaseTorneo::CONSOLACION, 1));

        $this->crearRonda('consolacion')->assertSessionHas('success');
        $jornada = Jornada::query()->whereKey(Partido::query()->whereKey($this->llave(FaseTorneo::CONSOLACION, 1)->partido_id)->value('jornada_id'))->sole();
        $this->assertSame(FaseTorneo::CONSOLACION, FaseTorneo::query()->whereKey($jornada->fase_id)->value('tipo'));

        $this->jugarRonda(FaseTorneo::CONSOLACION, 1);
        $this->crearRonda('consolacion');
        $this->assertSame([[5, 'E9', 9, 'E12', 12], [6, 'E10', 10, 'E11', 11]], $this->cruces(FaseTorneo::CONSOLACION, 2));
        $this->jugarRonda(FaseTorneo::CONSOLACION, 2);
        $this->crearRonda('consolacion');
        $this->jugarRonda(FaseTorneo::CONSOLACION, 3, [7 => [0, 1]]);

        $this->actingAs($this->presidente)->get(route('grupos.consolacion', $this->grupo))
            ->assertOk()
            ->assertSee('Campeón alterno: E10')
            ->assertSee('Subcampeón de consolación: E9')
            ->assertSee('La liguilla de consolación terminó: la final ya tiene resultado oficial.');
        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->whereHas('fase', fn ($fase) => $fase->where('tipo', FaseTorneo::LIGUILLA))->count());
    }

    // CA1 con menos equipos: juegan los que haya y, si no son 2, 4 u 8, el mejor ubicado pasa directo; sin al menos 2, no hay consolación
    public function test_con_menos_de_8_equipos_la_consolacion_juega_con_los_que_hay(): void
    {
        $this->grupoDe(11);

        $this->actingAs($this->presidente)->get(route('grupos.consolacion', $this->grupo))
            ->assertOk()
            ->assertSee('Vista previa con los lugares 9.° a 11.° de la tabla final (3 equipos)');
        $this->generar('consolacion')->assertSessionHas('success');
        $this->assertSame([[1, 'E9', 9, '', null], [2, 'E10', 10, 'E11', 11]], $this->cruces(FaseTorneo::CONSOLACION, 1));

        $this->crearRonda('consolacion');
        $this->jugarRonda(FaseTorneo::CONSOLACION, 1, [2 => [0, 2]]);
        $this->crearRonda('consolacion');
        $this->assertSame([[3, 'E9', 9, 'E11', 11]], $this->cruces(FaseTorneo::CONSOLACION, 2));

        $this->grupoDe(9);
        $this->assertSame(
            ['Después de los 8 lugares de la liguilla quedan 1 equipo(s) en la tabla: la liguilla de consolación necesita al menos 2.'],
            app(LiguillaService::class)->revisar($this->grupo, FaseTorneo::CONSOLACION)['problemas'],
        );
        $this->generar('consolacion')->assertSessionHasErrors('liguilla');
        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->count());
    }

    // Decisión de Daniel (27/11): los lugares salen de la configuración del grupo (HU-008), siguientes a la liguilla
    public function test_la_consolacion_usa_los_lugares_que_configura_el_grupo(): void
    {
        $this->grupoDe(16, liguilla: 4, consolacion: 4);
        $revision = app(LiguillaService::class)->revisar($this->grupo, FaseTorneo::CONSOLACION);
        $this->assertSame([5, 8], [$revision['desde'], $revision['hasta']]);
        $this->assertSame([['E5', 'E8'], ['E6', 'E7']], array_map(fn (array $cruce) => [$cruce['local']['nombre'], $cruce['visitante']['nombre'] ?? null], $revision['cruces']));

        $this->grupo->update(['criterios_clasificacion' => ['clasifican_liguilla' => 4, 'clasifican_consolacion' => 0]]);
        $this->assertSame(
            ['Configura en «Formato y clasificación» cuántos equipos del grupo clasifican a la liguilla de consolación (al menos 2).'],
            app(LiguillaService::class)->revisar($this->grupo->fresh() ?? $this->grupo, FaseTorneo::CONSOLACION)['problemas'],
        );
    }

    // Decisión 3 también en la consolación: si empatan, avanza el mejor ubicado de la tabla general
    public function test_en_empate_avanza_el_mejor_ubicado_en_la_consolacion(): void
    {
        $this->grupoDe(16);
        $this->generar('consolacion');
        $this->crearRonda('consolacion');

        $this->jugar(FaseTorneo::CONSOLACION, 1, 1, 1);

        $llave = $this->llave(FaseTorneo::CONSOLACION, 1);
        $this->assertSame($this->equipos['E9']->id, $llave->avanza_equipo_id);
        $this->assertSame($this->equipos['E16']->id, $llave->eliminadoId());
    }

    // Cada fase tiene sus llaves, rondas y jornadas: la consolación no cambia la liguilla ni al revés
    public function test_la_consolacion_no_se_mezcla_con_la_liguilla(): void
    {
        $this->grupoDe(16);
        $this->generar('liguilla')->assertSessionHas('success');
        $this->generar('consolacion')->assertSessionHas('success');
        $this->crearRonda('liguilla');
        $this->crearRonda('consolacion');

        $this->jugarRonda(FaseTorneo::LIGUILLA, 1);
        $this->crearRonda('liguilla')->assertSessionHas('success');

        $this->assertSame([[5, 'E1', 1, 'E4', 4], [6, 'E2', 2, 'E3', 3]], $this->cruces(FaseTorneo::LIGUILLA, 2));
        $this->assertSame([], $this->cruces(FaseTorneo::CONSOLACION, 2));
        $this->assertSame(1, $this->llave(FaseTorneo::LIGUILLA, 1)->orden);
        $this->assertSame(1, $this->llave(FaseTorneo::CONSOLACION, 1)->orden);
        $this->crearRonda('consolacion')->assertSessionHasErrors(['ronda' => 'La ronda 1 tiene 4 llave(s) sin resultado oficial. La siguiente ronda se crea cuando todas estén resueltas.']);

        $this->actingAs($this->presidente)->get(route('grupos.liguilla', $this->grupo))->assertOk()->assertDontSee('E13');
        $this->actingAs($this->presidente)->get(route('grupos.consolacion', $this->grupo))->assertOk()->assertDontSee('Semifinales');
    }

    // CA2: el campeón de liga (1°) juega contra el campeón de liguilla y el sistema registra el resultado
    public function test_se_registra_el_resultado_del_campeon_de_campeones(): void
    {
        $this->grupoDe(16);
        $this->generar('liguilla');
        $this->generar('campeon-de-campeones')->assertSessionHasErrors(['liguilla' => 'La liguilla del grupo todavía no tiene campeón: el Campeón de Campeones se define después de la final.']);

        // E8 elimina a E1; la final la gana E2
        $this->terminarLiguilla([1 => [0, 1]]);

        $this->actingAs($this->presidente)->get(route('grupos.campeon-de-campeones', $this->grupo))->assertOk()->assertSee('Registrar Campeón de Campeones');
        $this->generar('campeon-de-campeones')->assertSessionHas('success', 'Se registró la llave de Campeón de Campeones.');
        $this->assertSame([[1, 'E1', 1, 'E2', 2]], $this->cruces(FaseTorneo::CAMPEON_DE_CAMPEONES, 1));

        $this->crearRonda('campeon-de-campeones')->assertSessionHas('success');
        $llave = $this->llave(FaseTorneo::CAMPEON_DE_CAMPEONES, 1);
        $partido = Partido::query()->whereKey($llave->partido_id)->with('jornada')->sole();
        $this->assertSame(Partido::ESTADO_BORRADOR, $partido->estado);
        $this->assertSame([$this->equipos['E1']->id, $this->equipos['E2']->id], [$partido->equipo_local_id, $partido->equipo_visitante_id]);

        $this->jugar(FaseTorneo::CAMPEON_DE_CAMPEONES, 1, 0, 2);

        $llave->refresh();
        $this->assertSame([0, 2, $this->equipos['E2']->id], [$llave->marcador_global_local, $llave->marcador_global_visitante, $llave->avanza_equipo_id]);
        $this->actingAs($this->presidente)->get(route('grupos.campeon-de-campeones', $this->grupo))
            ->assertOk()
            ->assertSee('Campeón de Campeones: E2')
            ->assertSee('El Campeón de Campeones ya está definido.');
    }

    // Decisión 6: si el campeón de liga también gana la liguilla, es Campeón de Campeones sin partido
    public function test_si_el_campeon_de_liga_gana_la_liguilla_es_campeon_de_campeones_directo(): void
    {
        $this->grupoDe(16);
        $this->generar('liguilla');
        $this->terminarLiguilla();

        $this->generar('campeon-de-campeones')->assertSessionHas('success');

        $llave = $this->llave(FaseTorneo::CAMPEON_DE_CAMPEONES, 1);
        $this->assertTrue($llave->esPaseDirecto());
        $this->assertSame($this->equipos['E1']->id, $llave->avanza_equipo_id);
        $this->crearRonda('campeon-de-campeones')->assertSessionHasErrors(['ronda' => 'El Campeón de Campeones ya está definido.']);
        $this->assertNull($llave->fresh()?->partido_id);

        $this->actingAs($this->presidente)->get(route('grupos.campeon-de-campeones', $this->grupo))
            ->assertOk()
            ->assertSee('Campeón de Campeones: E1')
            ->assertSee('Ganó la liga y la liguilla, así que es Campeón de Campeones sin jugar.');
    }

    // Decisión 3 en el Campeón de Campeones: si empatan, gana el mejor ubicado, que es el campeón de liga
    public function test_en_empate_el_campeon_de_liga_gana_el_campeon_de_campeones(): void
    {
        $this->grupoDe(16);
        $this->generar('liguilla');
        $this->terminarLiguilla([1 => [0, 1]]);
        $this->generar('campeon-de-campeones');
        $this->crearRonda('campeon-de-campeones');

        $this->jugar(FaseTorneo::CAMPEON_DE_CAMPEONES, 1, 1, 1);

        $this->assertSame($this->equipos['E1']->id, $this->llave(FaseTorneo::CAMPEON_DE_CAMPEONES, 1)->avanza_equipo_id);
    }

    // CA3 y decisión 6: goleo de la fase regular de todos los grupos de la categoría; no cuentan la liguilla, los autogoles ni lo no validado
    public function test_el_campeon_de_goleo_de_cada_categoria_cuenta_solo_la_fase_regular(): void
    {
        [$grupo, $equipos] = $this->grupoConEquipos(['A', 'B']);
        $grupo->categoria?->update(['nombre' => 'Alfa']);
        [$otro, $deOtro] = $this->otroGrupo($grupo, ['C', 'D']);
        $this->otroGrupo($grupo, ['E', 'F'], categoria: 'Zeta');
        $ana = $this->jugador($equipos['A'], 'Ana', 'Arriaga');
        $beto = $this->jugador($deOtro['C'], 'Beto', 'Bravo');

        $this->goles($this->resultado($grupo, 1, $equipos['A'], 2, 0, $equipos['B'], validar: false), $ana, 2);
        $this->goles($this->resultado($otro, 1, $deOtro['C'], 1, 0, $deOtro['D'], validar: false), $beto, 1);
        // Tres goles de Beto en la liguilla, dos autogoles y una cédula sin validar no cuentan
        $this->goles($this->partidoDeLiguilla($otro, $deOtro['C'], $deOtro['D']), $beto, 3);
        $this->goles($this->resultado($otro, 2, $deOtro['C'], 2, 0, $deOtro['D'], validar: false), $beto, 2, autogol: true);
        $this->goles($this->resultado($otro, 3, $deOtro['C'], 4, 0, $deOtro['D'], validar: false), $beto, 4, validar: false);

        $campeones = app(EstadisticasGrupoService::class)->campeonesDeGoleo($grupo->temporada);
        $this->assertSame(
            [['Alfa', 2, ['Ana']], ['Zeta', 0, []]],
            array_map(fn (array $fila) => [$fila['categoria']->nombre, $fila['goles'], array_map(fn (Jugador $j) => $j->persona?->nombre, $fila['jugadores'])], $campeones),
        );

        $this->actingAs($this->presidente)->get(route('temporadas.goleo', $grupo->temporada))
            ->assertOk()
            ->assertSee('La temporada sigue abierta: se muestra el líder de goleo provisional.')
            ->assertSee('Ana Arriaga')
            ->assertSee('Sin goles en resultados oficiales de la fase regular.');

        $grupo->temporada->update(['estado' => 'cerrada']);
        $this->actingAs($this->presidente)->get(route('temporadas.goleo', $grupo->temporada))
            ->assertOk()
            ->assertSee('Campeón de goleo por categoría')
            ->assertDontSee('La temporada sigue abierta');
    }

    // Decisión 6: en empate gana el del equipo mejor ubicado en la tabla de su grupo; si siguen empatados, se comparte
    public function test_el_goleo_se_desempata_por_la_tabla_y_si_no_se_comparte(): void
    {
        [$grupo, $equipos] = $this->grupoConEquipos(['A', 'B', 'C']);
        [$otro, $deOtro] = $this->otroGrupo($grupo, ['D', 'E']);
        $ana = $this->jugador($equipos['A'], 'Ana', 'Arriaga');
        $bruno = $this->jugador($equipos['B'], 'Bruno', 'Barrios');
        $dora = $this->jugador($deOtro['D'], 'Dora', 'Duarte');

        // A queda 1.° (diferencia +1) y B 2.° (diferencia 0), los dos con 3 puntos; D es 1.° de su grupo. Los tres meten 2
        $primero = $this->resultado($grupo, 1, $equipos['A'], 2, 1, $equipos['B'], validar: false);
        $this->goles($primero, $bruno, 1, validar: false);
        $this->goles($primero, $ana, 2);
        $this->goles($this->resultado($grupo, 2, $equipos['B'], 1, 0, $equipos['C'], validar: false), $bruno, 1);
        $this->goles($this->resultado($otro, 1, $deOtro['D'], 2, 0, $deOtro['E'], validar: false), $dora, 2);

        $campeones = app(EstadisticasGrupoService::class)->campeonesDeGoleo($grupo->temporada);
        $this->assertSame(2, $campeones[0]['goles']);
        $this->assertSame([$ana->id, $dora->id], array_map(fn (Jugador $j) => $j->id, $campeones[0]['jugadores']));

        $this->actingAs($this->presidente)->get(route('temporadas.goleo', $grupo->temporada))
            ->assertOk()
            ->assertSee('(compartido)', false)
            ->assertDontSee('Bruno Barrios');
    }

    // Decisión 4: solo el Presidente y el Administrador General operan la consolación y el Campeón de Campeones (403 en español)
    public function test_solo_presidente_o_administrador_operan_consolacion_y_campeon_de_campeones(): void
    {
        $this->grupoDe(16);
        $fechas = ['fecha_inicio' => '2026-12-05', 'fecha_fin' => '2026-12-06'];

        $roles = Role::query()->whereNotIn('name', ['Presidente', 'Administrador General'])->pluck('name');
        $this->assertCount(11, $roles);
        foreach ($roles as $rol) {
            $usuario = $this->usuario($rol);
            $this->actingAs($usuario)->post(route('grupos.consolacion.store', $this->grupo))
                ->assertForbidden()->assertSee('Solo el Presidente o el Administrador General pueden generar las llaves de la liguilla de consolación.');
            $this->actingAs($usuario)->post(route('grupos.consolacion.rondas', $this->grupo), $fechas)
                ->assertForbidden()->assertSee('Solo el Presidente o el Administrador General pueden crear las rondas de la liguilla de consolación.');
            $this->actingAs($usuario)->post(route('grupos.campeon-de-campeones.store', $this->grupo))
                ->assertForbidden()->assertSee('Solo el Presidente o el Administrador General pueden registrar el Campeón de Campeones.');
            $this->actingAs($usuario)->post(route('grupos.campeon-de-campeones.rondas', $this->grupo), $fechas)
                ->assertForbidden()->assertSee('Solo el Presidente o el Administrador General pueden crear el partido de Campeón de Campeones.');
        }
        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->count());

        $this->actingAs($this->usuario('Administrador General'))->post(route('grupos.consolacion.store', $this->grupo))->assertSessionHas('success');

        $this->grupo->temporada->update(['estado' => 'cerrada']);
        $this->generar('campeon-de-campeones')
            ->assertForbidden()
            ->assertSee('La temporada de este grupo está cerrada: ya no se registra el Campeón de Campeones.');
        $this->crearRonda('consolacion')
            ->assertForbidden()
            ->assertSee('La temporada de este grupo está cerrada: ya no se crean rondas de liguilla de consolación.');
    }

    // CA3: el goleo lo consulta quien ve temporadas; los demás reciben 403 en español
    public function test_sin_permiso_no_ve_el_goleo(): void
    {
        [$grupo] = $this->grupoConEquipos(['A', 'B']);
        $sinRol = User::create([
            'name' => 'Sin rol',
            'email' => str()->uuid().'@liga.test',
            'password' => Hash::make('secreto123'),
            'estatus' => 'activo',
            'rol' => 'Consultor',
        ]);

        $this->actingAs($sinRol)->get(route('temporadas.goleo', $grupo->temporada))
            ->assertForbidden()
            ->assertSee('No cuenta con privilegios para consultar el campeón de goleo de la temporada.');
        $this->actingAs($this->usuario('Administrador General'))->get(route('temporadas.goleo', $grupo->temporada))->assertOk();
        $this->actingAs($this->presidente)->get(route('temporadas.show', $grupo->temporada))->assertOk()->assertSee('Líderes de goleo');
    }

    // Grupo con N equipos E1..EN y el rol completo jugado: la tabla queda E1, E2, ..., EN
    private function grupoDe(int $equipos, int $liguilla = 8, int $consolacion = 8): void
    {
        [$this->grupo, $this->equipos] = $this->grupoConEquipos(array_map(fn (int $n): string => 'E'.$n, range(1, $equipos)));
        $this->grupo->update(['criterios_clasificacion' => ['clasifican_liguilla' => $liguilla, 'clasifican_consolacion' => $consolacion]]);
        $this->jugarRolCompleto($this->grupo, array_values($this->equipos));
    }

    // Liguilla de 8 de principio a fin; $primeraRonda cambia el marcador de alguna llave de la primera ronda
    /** @param  array<int, array{0: int, 1: int}>  $primeraRonda */
    private function terminarLiguilla(array $primeraRonda = []): void
    {
        foreach ([1 => $primeraRonda, 2 => [], 3 => []] as $ronda => $marcadores) {
            $this->crearRonda('liguilla')->assertSessionHas('success');
            $this->jugarRonda(FaseTorneo::LIGUILLA, $ronda, $marcadores);
        }
    }

    private function generar(string $ruta): TestResponse
    {
        return $this->actingAs($this->presidente)->post(route("grupos.{$ruta}.store", $this->grupo));
    }

    private function crearRonda(string $ruta): TestResponse
    {
        return $this->actingAs($this->presidente)
            ->post(route("grupos.{$ruta}.rondas", $this->grupo), ['fecha_inicio' => '2026-12-05', 'fecha_fin' => '2026-12-06']);
    }

    // Juega las llaves de la ronda que tienen partido: gana 1-0 el local salvo los marcadores indicados por orden
    /** @param  array<int, array{0: int, 1: int}>  $marcadores */
    private function jugarRonda(string $tipo, int $ronda, array $marcadores = []): void
    {
        foreach ($this->llaves($tipo)->where('ronda', $ronda)->whereNotNull('partido_id') as $llave) {
            [$local, $visitante] = $marcadores[$llave->orden] ?? [1, 0];
            $this->jugar($tipo, (int) $llave->orden, $local, $visitante);
        }
    }

    private function jugar(string $tipo, int $orden, int $golesLocal, int $golesVisitante): void
    {
        $cedula = CedulaArbitral::create([
            'partido_id' => $this->llave($tipo, $orden)->partido_id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => $golesLocal,
            'goles_visitante' => $golesVisitante,
            'cerrada_en' => now(),
        ]);

        app(ValidacionResultadoService::class)->validar($cedula, $this->validador());
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, LlaveLiguilla> */
    private function llaves(string $tipo): \Illuminate\Database\Eloquent\Collection
    {
        return LlaveLiguilla::query()
            ->where('grupo_id', $this->grupo->id)
            ->whereHas('fase', fn ($fase) => $fase->where('tipo', $tipo))
            ->with('local:id,nombre', 'visitante:id,nombre')
            ->orderBy('orden')
            ->get();
    }

    private function llave(string $tipo, int $orden): LlaveLiguilla
    {
        return $this->llaves($tipo)->where('orden', $orden)->sole();
    }

    /**
     * @return list<array{0: int|null, 1: string, 2: int|null, 3: string, 4: int|null}>  orden, local, su lugar, visitante, su lugar
     */
    private function cruces(string $tipo, int $ronda): array
    {
        return $this->llaves($tipo)->where('ronda', $ronda)->values()
            ->map(fn (LlaveLiguilla $llave) => [$llave->orden, (string) $llave->local?->nombre, $llave->lugar_local, (string) $llave->visitante?->nombre, $llave->lugar_visitante])
            ->all();
    }

    /**
     * Otro grupo de la misma temporada, en la misma categoría o en una nueva con ese nombre, con un equipo por nombre.
     *
     * @param  list<string>  $nombres
     * @return array{0: Grupo, 1: array<string, Equipo>}
     */
    private function otroGrupo(Grupo $grupo, array $nombres, ?string $categoria = null): array
    {
        $categoriaId = $categoria === null
            ? $grupo->categoria_id
            : Categoria::factory()->create(['temporada_id' => $grupo->temporada_id, 'nombre' => $categoria])->id;
        $otro = Grupo::factory()->create(['categoria_id' => $categoriaId]);

        $equipos = [];
        foreach ($nombres as $nombre) {
            $equipos[$nombre] = Equipo::create([
                'club_id' => Club::create(['nombre' => 'Club '.$nombre.' '.$otro->id])->id,
                'grupo_id' => $otro->id,
                'nombre' => $nombre,
            ]);
        }

        return [$otro, $equipos];
    }

    private function jugador(Equipo $equipo, string $nombre, string $apellidos): Jugador
    {
        return Jugador::create([
            'persona_id' => Persona::factory()->create(['nombre' => $nombre, 'apellidos' => $apellidos])->id,
            'equipo_id' => $equipo->id,
            'dorsal' => random_int(1, 99),
            'estado_elegibilidad' => 'Activo',
        ]);
    }

    // Registra los goles del jugador en la cédula y, si $validar, la valida
    private function goles(CedulaArbitral $cedula, Jugador $jugador, int $cuantos, bool $autogol = false, bool $validar = true): void
    {
        for ($i = 0; $i < $cuantos; $i++) {
            EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $jugador->id, 'tipo' => 'Gol', 'autogol' => $autogol, 'minuto' => 10 + $i]);
        }

        if ($validar) {
            app(ValidacionResultadoService::class)->validar($cedula, $this->validador());
        }
    }

    // Partido de una jornada de liguilla (con fase) con su cédula cerrada, todavía sin validar
    private function partidoDeLiguilla(Grupo $grupo, Equipo $local, Equipo $visitante): CedulaArbitral
    {
        $fase = FaseTorneo::query()->firstOrCreate(['temporada_id' => $grupo->temporada_id, 'tipo' => FaseTorneo::LIGUILLA]);
        $jornada = Jornada::create(['grupo_id' => $grupo->id, 'fase_id' => $fase->id, 'numero_jornada' => 50, 'fecha_inicio' => '2026-12-05', 'fecha_fin' => '2026-12-06']);
        $partido = Partido::create(['jornada_id' => $jornada->id, 'equipo_local_id' => $local->id, 'equipo_visitante_id' => $visitante->id, 'estado' => 'programado']);

        return CedulaArbitral::create(['partido_id' => $partido->id, 'estado' => CedulaArbitral::CERRADA, 'goles_local' => 3, 'goles_visitante' => 0, 'cerrada_en' => now()]);
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
