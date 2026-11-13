<?php

namespace Tests\Feature;

use App\Models\CedulaArbitral;
use App\Models\Jugador;
use App\Models\Partido;
use App\Services\CedulaService;
use App\Services\GeneradorEnfrentamientosService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

// HU-036, corrección del #78 (RNF-30, RNF-33): un partido con la cédula capturada no se borra en cascada sin auditoría
class CedulaBorradoTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    private Partido $partido;

    private CedulaService $cedulas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->cedulas = app(CedulaService::class);
        $this->partido = $this->partidoConArbitro();
    }

    #[DataProvider('capturas')]
    public function test_no_se_borra_un_partido_con_la_cedula_capturada(string $captura): void
    {
        $cedula = $this->capturar($captura);

        $this->actingAs($this->usuario('Programador de Partidos'))
            ->from(route('jornadas.show', $this->partido->jornada_id))
            ->delete(route('partidos.destroy', $this->partido))
            ->assertRedirect(route('jornadas.show', $this->partido->jornada_id))
            ->assertSessionHasErrors(['estado' => 'No se puede eliminar un partido cuya cédula arbitral ya tiene datos capturados.']);

        $this->assertModelExists($this->partido);
        $this->assertModelExists($cedula);
        $this->assertSame(8, $cedula->alineaciones()->count());

        // El mensaje se ve en la jornada y en el partido, desde donde se borra
        foreach ([route('jornadas.show', $this->partido->jornada_id), route('partidos.show', $this->partido)] as $pantalla) {
            $this->from($pantalla)->followingRedirects()
                ->delete(route('partidos.destroy', $this->partido))
                ->assertOk()
                ->assertSee('No se puede eliminar un partido cuya cédula arbitral ya tiene datos capturados.');
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function capturas(): array
    {
        return [
            'marcador' => ['marcador'],
            'gol' => ['evento'],
            'incidencia' => ['incidencia'],
            'asistencia' => ['asistencia'],
        ];
    }

    // Por JSON la misma regla responde 422, no 500
    public function test_por_json_el_rechazo_es_un_422(): void
    {
        $this->capturar('evento');

        $this->actingAs($this->usuario('Programador de Partidos'))
            ->deleteJson(route('partidos.destroy', $this->partido))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['estado' => 'No se puede eliminar un partido cuya cédula arbitral ya tiene datos capturados.']);
    }

    public function test_no_se_borra_la_jornada_con_un_partido_con_la_cedula_capturada(): void
    {
        $cedula = $this->capturar('incidencia');

        $this->actingAs($this->usuario('Programador de Partidos'))
            ->delete(route('jornadas.destroy', $this->partido->jornada_id))
            ->assertSessionHasErrors(['jornada_id' => 'No se puede eliminar una jornada con partidos cuya cédula arbitral ya tiene datos capturados.']);

        $this->assertModelExists($this->partido);
        $this->assertSame(1, $cedula->incidencias()->count());
    }

    public function test_no_se_reemplaza_el_rol_con_un_partido_con_la_cedula_capturada(): void
    {
        $cedula = $this->capturar('marcador');
        $generador = app(GeneradorEnfrentamientosService::class);
        $grupo = $this->partido->jornada()->firstOrFail()->grupo()->firstOrFail();

        try {
            $inicio = (string) $grupo->categoria()->firstOrFail()->temporada()->firstOrFail()->getRawOriginal('fecha_inicio');
            $propuesta = $generador->generarPropuestaRol($grupo, ['fecha_inicio' => substr($inicio, 0, 10)]);
            $generador->guardarRolGenerado($grupo, $propuesta, true);
            $this->fail('Se esperaba el rechazo del reemplazo del rol.');
        } catch (ValidationException $error) {
            $this->assertSame(['grupo' => ['El grupo tiene partidos cuya cédula arbitral ya tiene datos capturados y su rol no puede ser reemplazado.']], $error->errors());
        }

        $this->assertModelExists($this->partido);
        $this->assertSame(1, $cedula->refresh()->goles_local);
    }

    // Un partido sin cédula, o con la cédula abierta pero vacía, se sigue borrando como hoy
    public function test_un_partido_sin_cedula_o_con_la_cedula_vacia_se_borra(): void
    {
        $sinCedula = $this->partidoConArbitro();
        $cedulaVacia = $this->cedulas->abrir($this->partido);
        $programador = $this->usuario('Programador de Partidos');

        foreach ([$sinCedula, $this->partido] as $partido) {
            $this->actingAs($programador)
                ->delete(route('partidos.destroy', $partido))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success', 'Partido eliminado de la programación.');
            $this->assertModelMissing($partido);
        }

        $this->assertModelMissing($cedulaVacia);
    }

    public function test_una_jornada_con_la_cedula_vacia_se_borra(): void
    {
        $this->cedulas->abrir($this->partido);

        $this->actingAs($this->usuario('Programador de Partidos'))
            ->delete(route('jornadas.destroy', $this->partido->jornada_id))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($this->partido);
    }

    private function capturar(string $captura): CedulaArbitral
    {
        $cedula = $this->cedulas->abrir($this->partido);
        $jugador = Jugador::query()->where('equipo_id', $this->partido->equipo_local_id)->where('estado_elegibilidad', 'Activo')->firstOrFail();
        $clave = (string) Str::uuid();

        match ($captura) {
            'marcador' => $this->cedulas->guardarMarcador($cedula, 1, 0, $clave),
            'evento' => $this->cedulas->registrarEvento($cedula, ['jugador_id' => $jugador->id, 'tipo' => 'Gol'], $clave),
            'incidencia' => $this->cedulas->registrarIncidencia($cedula, ['descripcion' => 'Lluvia'], $clave),
            default => $this->cedulas->guardarAlineacion($cedula, $jugador->id, ['asistio' => true], $clave),
        };

        return $cedula;
    }
}
