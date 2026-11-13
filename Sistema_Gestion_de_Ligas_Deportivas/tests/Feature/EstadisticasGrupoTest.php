<?php

namespace Tests\Feature;

use App\Models\CedulaArbitral;
use App\Models\Equipo;
use App\Models\EventoPartido;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\TablaPosicionJornada;
use App\Models\User;
use App\Services\EstadisticasGrupoService;
use App\Services\ValidacionResultadoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\TestCase;

// HU-043 (SCRUM-55, RF-09.3, RF-09.4): goleo, tarjetas, sanciones y tabla histórica por jornada
class EstadisticasGrupoTest extends TestCase
{
    use CreaGrupoConResultados;
    use DatabaseTransactions;

    private EstadisticasGrupoService $estadisticas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->estadisticas = app(EstadisticasGrupoService::class);
    }

    // CA1: los goles de resultados oficiales cuentan; los autogoles y los partidos sin validar no
    public function test_goleadores_solo_con_resultados_oficiales_y_sin_autogoles(): void
    {
        [$grupo, $e] = $this->grupoConEquipos();
        $ana = $this->jugador($e['A'], 'Ana', 'Ruiz');
        $beto = $this->jugador($e['B'], 'Beto', 'Soto');

        $primero = $this->resultado($grupo, 1, $e['A'], 2, 1, $e['B'], validar: false);
        $this->evento($primero, $ana, 'Gol');
        $this->evento($primero, $ana, 'Gol');
        $this->evento($primero, $beto, 'Gol', autogol: true);
        $this->validar($primero);

        $sinValidar = $this->resultado($grupo, 2, $e['B'], 3, 0, $e['A'], validar: false);
        $this->evento($sinValidar, $beto, 'Gol');
        $this->evento($sinValidar, $beto, 'Gol');
        $this->evento($sinValidar, $beto, 'Gol');

        $goleadores = $this->estadisticas->goleadores($grupo);

        $this->assertSame([[$ana->id, 2]], $goleadores->map(fn (array $fila) => [$fila['jugador']->id, $fila['goles']])->all());
    }

    // CA1: tarjetas acumuladas por jugador en todos sus partidos oficiales
    public function test_tarjetas_acumuladas_por_jugador(): void
    {
        [$grupo, $e] = $this->grupoConEquipos();
        $ana = $this->jugador($e['A'], 'Ana', 'Ruiz');
        $beto = $this->jugador($e['B'], 'Beto', 'Soto');

        $j1 = $this->resultado($grupo, 1, $e['A'], 0, 0, $e['B'], validar: false);
        $this->evento($j1, $ana, 'Amarilla');
        $this->evento($j1, $beto, 'Amarilla');
        $this->validar($j1);

        $j2 = $this->resultado($grupo, 2, $e['B'], 1, 1, $e['A'], validar: false);
        $this->evento($j2, $ana, 'Amarilla');
        $this->evento($j2, $ana, 'Roja');
        $this->validar($j2);

        $this->assertSame(
            [[$ana->id, 2, 1], [$beto->id, 1, 0]],
            $this->estadisticas->tarjetas($grupo)->map(fn (array $fila) => [$fila['jugador']->id, $fila['amarillas'], $fila['rojas']])->all()
        );
    }

    // CA1: las sanciones del Tribunal de Penas (HU-045) se muestran si su expediente es de un partido del grupo
    public function test_sanciones_de_partidos_del_grupo(): void
    {
        [$grupo, $e] = $this->grupoConEquipos();
        [$otroGrupo, $o] = $this->grupoConEquipos();
        $ana = $this->jugador($e['A'], 'Ana', 'Ruiz');
        $otro = $this->jugador($o['A'], 'Otro', 'Jugador');

        $this->sancion($this->resultado($grupo, 1, $e['A'], 1, 0, $e['B']), $ana, 'Juego brusco grave', 2);
        $this->sancion($this->resultado($otroGrupo, 1, $o['A'], 1, 0, $o['B']), $otro, 'De otro grupo', 1);

        $sanciones = $this->estadisticas->sanciones($grupo);

        $this->assertCount(1, $sanciones);
        $this->assertSame('Juego brusco grave', $sanciones->first()->descripcion_sancion);
        $this->assertSame('Vigente', $sanciones->first()->estado);
    }

    // CA2: la copia de la tabla se guarda al cerrar la jornada (todos sus partidos oficiales) y no cambia después
    public function test_se_conserva_la_tabla_al_cierre_de_cada_jornada(): void
    {
        [$grupo, $e] = $this->grupoConEquipos();

        $pendiente = $this->resultado($grupo, 1, $e['C'], 2, 2, $e['D'], validar: false);
        $this->resultado($grupo, 1, $e['A'], 1, 0, $e['B']);
        $this->assertSame(0, TablaPosicionJornada::query()->where('grupo_id', $grupo->id)->count());

        $this->validar($pendiente);
        $jornada1 = $pendiente->partido->jornada;
        $this->assertSame(
            ['A' => [1, 1, 3], 'C' => [2, 1, 1], 'D' => [3, 1, 1], 'B' => [4, 1, 0]],
            $this->copia($jornada1->id)
        );

        // Los partidos de la jornada se programan antes de capturar sus resultados
        $segundo = $this->resultado($grupo, 2, $e['D'], 1, 0, $e['C'], validar: false);
        $this->resultado($grupo, 2, $e['B'], 3, 0, $e['A']);
        $this->assertSame(4, TablaPosicionJornada::query()->where('grupo_id', $grupo->id)->count());
        $this->validar($segundo);

        // La copia de la jornada 1 sigue igual y se agrega la de la jornada 2
        $this->assertSame(['A' => [1, 1, 3], 'C' => [2, 1, 1], 'D' => [3, 1, 1], 'B' => [4, 1, 0]], $this->copia($jornada1->id));
        $this->assertSame(
            // B y A empatan a 3 puntos; B va arriba por diferencia de goles (+2 contra -2)
            ['D' => [1, 2, 4], 'B' => [2, 2, 3], 'A' => [3, 2, 3], 'C' => [4, 2, 1]],
            $this->copia($grupo->jornadas()->where('numero_jornada', 2)->value('id'))
        );
    }

    // CA2: si una jornada anterior sigue abierta, la siguiente no se copia todavía; se copian ambas al cerrar la anterior
    public function test_una_jornada_no_se_copia_si_la_anterior_sigue_abierta(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B']);
        $j1 = $this->resultado($grupo, 1, $e['A'], 1, 0, $e['B'], validar: false);
        $this->resultado($grupo, 2, $e['B'], 2, 0, $e['A']);

        $this->assertSame(0, TablaPosicionJornada::query()->where('grupo_id', $grupo->id)->count());

        $this->validar($j1);

        $this->assertSame(['A' => [1, 1, 3], 'B' => [2, 1, 0]], $this->copia($j1->partido->jornada_id));
        $this->assertSame(4, TablaPosicionJornada::query()->where('grupo_id', $grupo->id)->count());
    }

    // CA3 (RF-09.4): la pantalla distingue el dato oficial del indicador calculado y muestra la tabla por jornada
    public function test_la_pantalla_distingue_oficial_de_calculado(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['Leones', 'Tigres']);
        $ana = $this->jugador($e['Leones'], 'Ana', 'Ruiz');
        $cedula = $this->resultado($grupo, 1, $e['Leones'], 1, 0, $e['Tigres'], validar: false);
        $this->evento($cedula, $ana, 'Gol');
        $this->validar($cedula);

        $this->actingAs($this->usuario('Secretario'))
            ->get(route('grupos.estadisticas', $grupo))
            ->assertOk()
            ->assertSeeInOrder(['Resultados oficiales', 'Oficial', 'Leones vs Tigres', '1 - 0'])
            ->assertSeeInOrder(['Goleo', 'Calculado', 'Ana Ruiz', '1'])
            ->assertSeeInOrder(['Tabla por jornada', 'Calculado', 'Tabla al cierre de la jornada 1', 'Leones', 'Tigres']);

        $this->actingAs($this->usuario('Secretario'))
            ->get(route('categorias.show', $grupo->categoria))
            ->assertSee('href="'.route('grupos.estadisticas', $grupo).'"', false);
    }

    // Una jornada de otro grupo no se muestra en este; sin permiso para consultar grupos no se entra
    public function test_jornada_ajena_y_permisos(): void
    {
        [$grupo] = $this->grupoConEquipos(['A', 'B']);
        [$otro, $o] = $this->grupoConEquipos(['C', 'D']);
        $ajena = $this->resultado($otro, 1, $o['C'], 1, 0, $o['D']);

        $this->actingAs($this->usuario('Secretario'))
            ->get(route('grupos.estadisticas', ['grupo' => $grupo, 'jornada' => $ajena->partido->jornada_id]))
            ->assertNotFound();

        $this->actingAs($this->usuario('Tesorero'))->get(route('grupos.estadisticas', $grupo))->assertForbidden();
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

    private function evento(CedulaArbitral $cedula, Jugador $jugador, string $tipo, bool $autogol = false): void
    {
        EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $jugador->id, 'tipo' => $tipo, 'autogol' => $autogol, 'minuto' => 10]);
    }

    private function validar(CedulaArbitral $cedula): void
    {
        app(ValidacionResultadoService::class)->validar($cedula, $this->validador());
    }

    private function sancion(CedulaArbitral $cedula, Jugador $jugador, string $descripcion, int $partidos): void
    {
        $articulo = DB::table('catalogo_articulos')->insertGetId(['codigo' => 'ART-'.uniqid(), 'descripcion' => 'Artículo de prueba', 'created_at' => now(), 'updated_at' => now()]);
        $expediente = DB::table('expedientes_disciplinarios')->insertGetId(['cedula_id' => $cedula->id, 'partido_id' => $cedula->partido_id, 'articulo_id' => $articulo, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sanciones')->insert([
            'expediente_id' => $expediente,
            'persona_id' => $jugador->persona_id,
            'descripcion_sancion' => $descripcion,
            'partidos_suspendidos' => $partidos,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, list<int>>  equipo => [posición, PJ, puntos]
     */
    private function copia(int $jornadaId): array
    {
        return TablaPosicionJornada::query()
            ->where('jornada_id', $jornadaId)
            ->with('equipo:id,nombre')
            ->orderBy('posicion')
            ->get()
            ->mapWithKeys(fn (TablaPosicionJornada $fila) => [(string) $fila->equipo?->nombre => [$fila->posicion, $fila->pj, $fila->puntos]])
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
