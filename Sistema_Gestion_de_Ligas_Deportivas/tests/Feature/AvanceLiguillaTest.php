<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\CedulaArbitral;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\LlaveLiguilla;
use App\Models\Partido;
use App\Models\TablaPosicion;
use App\Models\TablaPosicionJornada;
use App\Models\User;
use App\Services\AvanceLiguillaService;
use App\Services\GestionEstadoPartidoService;
use App\Services\ProgramacionJornadaService;
use App\Services\ValidacionResultadoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\TestCase;

// HU-056 (SCRUM-86, RF-11.2): avances, eliminaciones, campeón y subcampeón de la liguilla
class AvanceLiguillaTest extends TestCase
{
    use CreaGrupoConResultados;
    use DatabaseTransactions;

    private Grupo $grupo;

    /** @var array<string, \App\Models\Equipo> */
    private array $equipos;

    private User $presidente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        // Fase regular completa: el de número menor gana 1-0, así la tabla queda E1, E2, ..., E8
        [$this->grupo, $this->equipos] = $this->grupoConEquipos(['E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7', 'E8']);
        $this->clasifican(8);
        $this->jugarRolCompleto($this->grupo, array_values($this->equipos));
        $this->presidente = $this->usuario('Presidente');
    }

    // CA1: al validar el resultado, el ganador avanza y el otro queda eliminado, con el avance en la bitácora
    public function test_el_ganador_avanza_y_el_perdedor_queda_eliminado(): void
    {
        $this->generarYCrearRonda();

        $partidos = Partido::query()->whereHas('llaveLiguilla')->with('jornada')->get();
        $this->assertCount(4, $partidos);
        $this->assertTrue($partidos->every(fn (Partido $p): bool => $p->estado === Partido::ESTADO_BORRADOR && $p->jornada->fase_id !== null));
        $this->assertSame(['2026-12-05', '2026-12-06'], [$partidos[0]->jornada->fecha_inicio->toDateString(), $partidos[0]->jornada->fecha_fin->toDateString()]);

        $this->jugar(1, 1, 2);

        $llave = $this->llave(1);
        $this->assertSame($this->equipos['E8']->id, $llave->avanza_equipo_id);
        $this->assertSame($this->equipos['E1']->id, $llave->eliminadoId());
        $this->assertSame([1, 2], [$llave->marcador_global_local, $llave->marcador_global_visitante]);
        $this->assertTrue(AuditoriaLog::query()->where('tabla_afectada', 'llaves_liguilla')->where('registro_id', $llave->id)
            ->where('motivo', 'like', 'Validación oficial del resultado%')->exists());

        $this->actingAs($this->presidente)->get(route('grupos.liguilla', $this->grupo))
            ->assertOk()
            ->assertSee('Cuartos de final')
            ->assertSee('Avanza E8')
            ->assertSee('Eliminado: E1');
    }

    // CA1 y decisión 3: si empatan, avanza el mejor ubicado de la tabla general; no hay penales
    public function test_en_empate_avanza_el_mejor_ubicado(): void
    {
        $this->generarYCrearRonda();

        $this->jugar(2, 1, 1);

        $llave = $this->llave(2);
        $this->assertSame($this->equipos['E2']->id, $llave->avanza_equipo_id);
        $this->assertSame($this->equipos['E7']->id, $llave->eliminadoId());
    }

    // Solo el resultado oficial cuenta: una cédula cerrada sin validar no hace avanzar a nadie
    public function test_un_resultado_sin_validar_no_avanza(): void
    {
        $this->generarYCrearRonda();
        foreach ([1, 2, 3] as $orden) {
            $this->jugar($orden, 2, 0);
        }
        $this->jugar(4, 2, 0, validar: false);

        $this->assertFalse($this->llave(4)->estaResuelta());

        $this->actingAs($this->presidente)
            ->post(route('grupos.liguilla.rondas', $this->grupo), ['fecha_inicio' => '2026-12-12', 'fecha_fin' => '2026-12-13'])
            ->assertSessionHasErrors(['ronda' => 'La ronda 1 tiene 1 llave(s) sin resultado oficial. La siguiente ronda se crea cuando todas estén resueltas.']);
        $this->assertSame(0, LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->where('ronda', 2)->count());
    }

    // Decisión de Daniel (25/11): la ronda siguiente se re-siembra, el mejor ubicado contra el peor; CA2: la final da campeón y subcampeón
    public function test_resiembra_las_rondas_y_la_final_da_campeon_y_subcampeon(): void
    {
        $this->generarYCrearRonda();
        $this->jugar(1, 0, 1); // E8 elimina a E1
        $this->jugar(2, 1, 0); // E2
        $this->jugar(3, 1, 0); // E3
        $this->jugar(4, 1, 0); // E4

        $this->crearRonda('2026-12-12', '2026-12-13')->assertSessionHas('success');
        // Con cuadro fijo E8 jugaría contra E4; al re-sembrar juega contra el mejor ubicado que sigue, E2
        $this->assertSame([[5, 'E2', 2, 'E8', 8], [6, 'E3', 3, 'E4', 4]], $this->llavesDeLaRonda(2));

        $this->jugar(5, 3, 1); // E2
        $this->jugar(6, 0, 2); // E4
        $this->crearRonda('2026-12-19', '2026-12-20');
        $this->assertSame([[7, 'E2', 2, 'E4', 4]], $this->llavesDeLaRonda(3));

        $this->jugar(7, 0, 0); // empate en la final: campeón el mejor ubicado

        $this->actingAs($this->presidente)->get(route('grupos.liguilla', $this->grupo))
            ->assertOk()
            ->assertSeeInOrder(['Cuartos de final', 'Semifinales', 'Final'])
            ->assertSee('Campeón: E2')
            ->assertSee('Subcampeón: E4')
            ->assertSee('La liguilla terminó: la final ya tiene resultado oficial.')
            ->assertDontSee('Crear la ronda');

        $this->crearRonda('2026-12-26', '2026-12-27')
            ->assertSessionHasErrors(['ronda' => 'La liguilla terminó: la final ya tiene resultado oficial.']);
    }

    // Decisión de Daniel (25/11): con 6 clasificados, 1° y 2° pasan directo y juegan 3° vs 6° y 4° vs 5°
    public function test_con_seis_clasificados_los_mejores_pasan_directo(): void
    {
        $this->clasifican(6);
        $this->actingAs($this->presidente)->get(route('grupos.liguilla', $this->grupo))->assertSee('Pasa directo');

        $this->generarYCrearRonda();

        $this->assertSame([[1, 'E1', 1, '', null], [2, 'E2', 2, '', null], [3, 'E3', 3, 'E6', 6], [4, 'E4', 4, 'E5', 5]], $this->llavesDeLaRonda(1));
        $this->assertSame($this->equipos['E1']->id, $this->llave(1)->avanza_equipo_id);
        $this->assertNull($this->llave(1)->partido_id);
        $this->assertSame(2, Partido::query()->whereHas('llaveLiguilla')->count());

        $this->jugar(3, 0, 1); // E6 elimina a E3
        $this->jugar(4, 1, 0); // E4
        $this->crearRonda('2026-12-12', '2026-12-13');

        $this->assertSame([[5, 'E1', 1, 'E6', 6], [6, 'E2', 2, 'E4', 4]], $this->llavesDeLaRonda(2));
        $this->actingAs($this->presidente)->get(route('grupos.liguilla', $this->grupo))->assertSee('Semifinales');
    }

    // Los partidos de liguilla no cambian la tabla de la fase regular ni sus copias por jornada (HU-041 y HU-043)
    public function test_la_liguilla_no_cambia_la_tabla_de_la_fase_regular(): void
    {
        $antes = TablaPosicion::query()->where('grupo_id', $this->grupo->id)->orderBy('equipo_id')->get(['equipo_id', 'pj', 'puntos', 'gf'])->toArray();

        $this->generarYCrearRonda();
        // La ronda completa: con todos sus resultados oficiales, la jornada de liguilla tampoco guarda copia de la tabla
        foreach ([1, 2, 3, 4] as $orden) {
            $this->jugar($orden, 0, 5);
        }

        $this->assertSame($antes, TablaPosicion::query()->where('grupo_id', $this->grupo->id)->orderBy('equipo_id')->get(['equipo_id', 'pj', 'puntos', 'gf'])->toArray());
        $jornada = Jornada::query()->where('grupo_id', $this->grupo->id)->whereNotNull('fase_id')->sole();
        $this->assertFalse(TablaPosicionJornada::query()->where('jornada_id', $jornada->id)->exists());
    }

    // Concurrencia: un segundo envío no crea otra ronda y una llave resuelta no avanza a nadie otra vez
    public function test_no_crea_dos_rondas_ni_avanza_dos_veces(): void
    {
        $this->generarYCrearRonda();
        $this->crearRonda('2026-12-05', '2026-12-06')->assertSessionHasErrors('ronda');

        $this->assertSame(1, Jornada::query()->where('grupo_id', $this->grupo->id)->whereNotNull('fase_id')->count());
        $this->assertSame(4, Partido::query()->whereHas('llaveLiguilla')->count());

        $cedula = $this->jugar(1, 2, 0);
        try {
            app(ValidacionResultadoService::class)->validar($cedula, $this->validador());
            $this->fail('Se validó dos veces el mismo resultado.');
        } catch (ValidationException) {
        }

        // Aunque el oyente corriera otra vez con otro marcador, la llave ya resuelta no cambia
        $cedula->forceFill(['goles_local' => 0, 'goles_visitante' => 3])->save();
        app(AvanceLiguillaService::class)->resolverLlave($cedula->fresh());
        $this->assertSame($this->equipos['E1']->id, $this->llave(1)->avanza_equipo_id);
    }

    // Decisión 4: solo el Presidente y el Administrador General crean rondas; los demás reciben 403 en español
    public function test_solo_presidente_o_administrador_crean_rondas(): void
    {
        $this->actingAs($this->presidente)->post(route('grupos.liguilla.store', $this->grupo));

        $roles = Role::query()->whereNotIn('name', ['Presidente', 'Administrador General'])->pluck('name');
        $this->assertCount(11, $roles);
        foreach ($roles as $rol) {
            $this->actingAs($this->usuario($rol))
                ->post(route('grupos.liguilla.rondas', $this->grupo), ['fecha_inicio' => '2026-12-05', 'fecha_fin' => '2026-12-06'])
                ->assertForbidden()
                ->assertSee('Solo el Presidente o el Administrador General pueden crear las rondas de la liguilla.');
        }
        $this->assertSame(0, Partido::query()->whereHas('llaveLiguilla')->count());

        $this->actingAs($this->usuario('Administrador General'))
            ->post(route('grupos.liguilla.rondas', $this->grupo), ['fecha_inicio' => '2026-12-05', 'fecha_fin' => '2026-12-06'])
            ->assertSessionHas('success');
        $this->assertSame(4, Partido::query()->whereHas('llaveLiguilla')->count());

        $this->grupo->temporada->update(['estado' => 'cerrada']);
        $this->crearRonda('2026-12-12', '2026-12-13')
            ->assertForbidden()
            ->assertSee('La temporada de este grupo está cerrada: ya no se crean rondas de liguilla.');
    }

    // Las fechas de la ronda se validan con mensajes en español; un arreglo en el campo no rompe la pantalla
    public function test_valida_las_fechas_de_la_ronda(): void
    {
        $this->actingAs($this->presidente)->post(route('grupos.liguilla.store', $this->grupo));
        $desde = route('grupos.liguilla', $this->grupo);

        $this->actingAs($this->presidente)->from($desde)
            ->post(route('grupos.liguilla.rondas', $this->grupo), [])
            ->assertSessionHasErrors(['fecha_inicio' => 'Indica la fecha de inicio de la ronda.', 'fecha_fin' => 'Indica la fecha de fin de la ronda.']);
        $this->actingAs($this->presidente)->from($desde)
            ->post(route('grupos.liguilla.rondas', $this->grupo), ['fecha_inicio' => '2026-12-06', 'fecha_fin' => '2026-12-05'])
            ->assertSessionHasErrors(['fecha_fin' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.']);
        $this->actingAs($this->presidente)->from($desde)
            ->post(route('grupos.liguilla.rondas', $this->grupo), ['fecha_inicio' => ['2026-12-05'], 'fecha_fin' => '06/12/2026'])
            ->assertSessionHasErrors(['fecha_inicio' => 'La fecha de inicio no es válida.', 'fecha_fin' => 'La fecha de fin debe tener el formato AAAA-MM-DD.']);

        $this->actingAs($this->presidente)->get($desde)->assertOk()->assertSee('Crear la ronda 1');
        $this->assertSame(0, Partido::query()->whereHas('llaveLiguilla')->count());
    }

    // Un partido de liguilla no cambia de equipos, no se elimina ni se cancela: su llave quedaría sin resolverse
    public function test_un_partido_de_liguilla_conserva_su_llave(): void
    {
        $this->generarYCrearRonda();
        $partido = Partido::query()->whereKey($this->llave(1)->partido_id)->with('jornada.grupo.temporada')->sole();
        $programacion = app(ProgramacionJornadaService::class);

        $this->assertRechaza(fn () => $programacion->actualizarPartido($partido, ['equipo_local_id' => $this->equipos['E5']->id]), 'sus equipos los define la llave');
        $this->assertRechaza(fn () => $programacion->eliminarPartido($partido), 'no se elimina porque su llave');
        $this->assertRechaza(fn () => $programacion->eliminarJornada($partido->jornada), 'Es una jornada de liguilla');
        $this->assertRechaza(fn () => app(GestionEstadoPartidoService::class)->cambiarEstado($partido, Partido::ESTADO_CANCELADO, 'Lluvia intensa en la cancha', $this->presidente), 'no se cancela');

        $this->assertSame(Partido::ESTADO_BORRADOR, $partido->fresh()?->estado);
        $this->assertSame($this->equipos['E1']->id, $partido->fresh()?->equipo_local_id);
    }

    private function generarYCrearRonda(): void
    {
        $this->actingAs($this->presidente)->post(route('grupos.liguilla.store', $this->grupo))->assertSessionHas('success');
        $this->crearRonda('2026-12-05', '2026-12-06')->assertSessionHas('success');
    }

    private function crearRonda(string $inicio, string $fin): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->presidente)
            ->post(route('grupos.liguilla.rondas', $this->grupo), ['fecha_inicio' => $inicio, 'fecha_fin' => $fin]);
    }

    // Cierra la cédula del partido de la llave con ese marcador y, si $validar, la valida (HU-040)
    private function jugar(int $orden, int $golesLocal, int $golesVisitante, bool $validar = true): CedulaArbitral
    {
        $cedula = CedulaArbitral::create([
            'partido_id' => $this->llave($orden)->partido_id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => $golesLocal,
            'goles_visitante' => $golesVisitante,
            'cerrada_en' => now(),
        ]);

        if ($validar) {
            app(ValidacionResultadoService::class)->validar($cedula, $this->validador());
        }

        return $cedula;
    }

    private function llave(int $orden): LlaveLiguilla
    {
        return LlaveLiguilla::query()->where('grupo_id', $this->grupo->id)->where('orden', $orden)->sole();
    }

    /**
     * @return list<array{0: int, 1: string, 2: int|null, 3: string, 4: int|null}>  orden, local, su lugar, visitante, su lugar
     */
    private function llavesDeLaRonda(int $ronda): array
    {
        return LlaveLiguilla::query()
            ->where('grupo_id', $this->grupo->id)
            ->where('ronda', $ronda)
            ->with('local:id,nombre', 'visitante:id,nombre')
            ->orderBy('orden')
            ->get()
            ->map(fn (LlaveLiguilla $llave) => [$llave->orden, (string) $llave->local?->nombre, $llave->lugar_local, (string) $llave->visitante?->nombre, $llave->lugar_visitante])
            ->all();
    }

    private function assertRechaza(callable $operacion, string $mensaje): void
    {
        try {
            $operacion();
            $this->fail('Se permitió una operación que debía rechazarse: '.$mensaje);
        } catch (ValidationException $e) {
            $this->assertStringContainsString($mensaje, implode(' ', $e->validator->errors()->all()));
        }
    }

    private function clasifican(int $equipos): void
    {
        $this->grupo->update(['criterios_clasificacion' => ['clasifican_liguilla' => $equipos, 'clasifican_consolacion' => 0]]);
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
