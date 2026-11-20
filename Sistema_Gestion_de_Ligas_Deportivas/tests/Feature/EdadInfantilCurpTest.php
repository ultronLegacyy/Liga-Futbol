<?php

namespace Tests\Feature;

use App\Models\AlineacionPartido;
use App\Models\Categoria;
use App\Models\CedulaArbitral;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\Temporada;
use App\Services\CedulaService;
use App\Services\ReglaEdadCategoriaService;
use App\Support\Curp;
use Database\Factories\PersonaFactory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaPartidoConCedula;
use Tests\TestCase;

/** HU-021 (SCRUM-36, RF-04.1, RF-04.4): la edad de los jugadores de la rama infantil se valida contra su CURP. */
class EdadInfantilCurpTest extends TestCase
{
    use CreaPartidoConCedula;
    use DatabaseTransactions;

    /** @return array<string, array{string, string|null}> */
    public static function curps(): array
    {
        return [
            'nacido en 2014 (letra en la posición 17)' => ['GOMA140215HDFRRNA5', '2014-02-15'],
            'nacido en 1995 (dígito en la posición 17)' => ['GOMA950215HDFRRN05', '1995-02-15'],
            'minúsculas y espacios' => [' goma121130mdfrrnb7 ', '2012-11-30'],
            'mes 13' => ['GOMA141315HDFRRNA5', null],
            '30 de febrero' => ['GOMA140230HDFRRNA5', null],
            'formato inválido' => ['GOMA14021', null],
        ];
    }

    #[DataProvider('curps')]
    public function test_lee_la_fecha_de_nacimiento_de_la_curp(string $curp, ?string $esperada): void
    {
        $this->assertSame($esperada, Curp::fechaNacimiento($curp)?->toDateString());
    }

    public function test_la_factory_genera_curps_que_coinciden_con_la_fecha_de_nacimiento(): void
    {
        foreach (['2014-02-15', '1999-12-31', '2000-01-01'] as $fecha) {
            $persona = Persona::factory()->make(['fecha_nacimiento' => $fecha]);
            $this->assertSame($fecha, Curp::fechaNacimiento($persona->curp)?->toDateString());
        }
    }

    // Criterio 1: al registrar se compara el año de la CURP con el rango de la categoría elegida
    public function test_al_registrar_se_compara_el_anio_de_la_curp_con_la_categoria(): void
    {
        [$categoria, $temporada] = $this->categoriaInfantil();
        $servicio = app(ReglaEdadCategoriaService::class);

        $servicio->validar($this->persona('2014-02-15', '2014-02-15'), $categoria, $temporada);

        try {
            // La fecha capturada (2014) cae en la categoría, pero la CURP dice 2012
            $servicio->validar($this->persona('2014-02-15', '2012-02-15'), $categoria, $temporada);
            $this->fail('Se registró a un jugador cuya CURP no corresponde a la categoría.');
        } catch (ValidationException $error) {
            $this->assertSame(
                ['Según su CURP, el jugador nació el 15/02/2012: no cumple el criterio de edad de la categoría Infantil (nacidos de 2013 a 2014).'],
                $error->errors()['equipo_id']
            );
        }
    }

    public function test_una_curp_sin_fecha_valida_no_se_inscribe_en_la_rama_infantil(): void
    {
        [$categoria, $temporada] = $this->categoriaInfantil();
        $persona = Persona::factory()->create(['fecha_nacimiento' => '2014-02-15', 'curp' => 'GOMA141315HDFRRNA5']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La CURP del jugador no tiene una fecha de nacimiento válida');

        app(ReglaEdadCategoriaService::class)->validar($persona, $categoria, $temporada);
    }

    // Solo aplica a la rama infantil y a categorías con criterio de edad
    public function test_las_otras_ramas_y_las_categorias_sin_criterio_no_revisan_la_curp(): void
    {
        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => '2026-08-01']);
        $servicio = app(ReglaEdadCategoriaService::class);
        $persona = $this->persona('1990-05-10', '2012-02-15');

        $servicio->validar($persona, Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => 'Libre', 'nombre' => 'Primera Fuerza', 'edad_min' => 18]), $temporada);
        $servicio->validar($persona, Categoria::factory()->create(['temporada_id' => $temporada->id, 'rama' => 'Infantil', 'nombre' => 'Infantil sin criterio']), $temporada);

        $this->addToAssertionCount(1);
    }

    // Criterio 2: al alinear en la cédula se bloquea y se explica el motivo
    public function test_no_se_alinea_en_la_cedula_a_quien_no_cumple_la_edad_segun_su_curp(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        [$partido, $cedula] = $this->cedulaInfantil();
        $jugador = Jugador::query()->where('equipo_id', $partido->equipo_local_id)->where('estado_elegibilidad', 'Activo')->firstOrFail();
        $jugador->persona->forceFill(['fecha_nacimiento' => '2014-02-15', 'curp' => PersonaFactory::curpPara('2011-02-15')])->save();
        $capturista = $this->usuario('Capturista');

        $this->actingAs($capturista)
            ->putJson(route('cedulas.alineacion', [$partido, $jugador->id]), ['asistio' => true, 'clave_envio' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['asistio' => 'Según su CURP, el jugador nació el 15/02/2011: no cumple el criterio de edad de la categoría Infantil (nacidos de 2013 a 2014).']);

        $this->assertFalse(AlineacionPartido::query()->where('cedula_id', $cedula->id)->where('jugador_id', $jugador->id)->sole()->asistio);
    }

    public function test_quien_cumple_la_edad_segun_su_curp_si_se_alinea(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        [$partido, $cedula] = $this->cedulaInfantil();
        $jugador = Jugador::query()->where('equipo_id', $partido->equipo_local_id)->where('estado_elegibilidad', 'Activo')->firstOrFail();

        $this->actingAs($this->usuario('Capturista'))
            ->putJson(route('cedulas.alineacion', [$partido, $jugador->id]), ['asistio' => true, 'clave_envio' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('asistio', true);

        $this->assertTrue(AlineacionPartido::query()->where('cedula_id', $cedula->id)->where('jugador_id', $jugador->id)->sole()->asistio);
    }

    /** @return array{Categoria, Temporada} */
    private function categoriaInfantil(): array
    {
        $temporada = Temporada::factory()->activa()->create(['fecha_inicio' => '2026-08-01']);
        $categoria = Categoria::factory()->create([
            'temporada_id' => $temporada->id,
            'rama' => 'Infantil',
            'nombre' => 'Infantil',
            'anio_nacimiento_desde' => 2013,
            'anio_nacimiento_hasta' => 2014,
        ]);

        return [$categoria, $temporada];
    }

    private function persona(string $fechaCapturada, string $fechaDeLaCurp): Persona
    {
        return Persona::factory()->create(['fecha_nacimiento' => $fechaCapturada, 'curp' => PersonaFactory::curpPara($fechaDeLaCurp)]);
    }

    /**
     * Partido de la categoría Infantil (nacidos de 2013 a 2014) con su cédula abierta; todos los jugadores nacieron en 2014.
     *
     * @return array{Partido, CedulaArbitral}
     */
    private function cedulaInfantil(): array
    {
        $partido = $this->partidoConArbitro();
        $partido->jornada->grupo->categoria->update(['rama' => 'Infantil', 'nombre' => 'Infantil', 'anio_nacimiento_desde' => 2013, 'anio_nacimiento_hasta' => 2014]);

        Jugador::query()->whereIn('equipo_id', [$partido->equipo_local_id, $partido->equipo_visitante_id])->with('persona')->get()
            ->each(fn (Jugador $jugador) => $jugador->persona->forceFill(['fecha_nacimiento' => '2014-05-01', 'curp' => PersonaFactory::curpPara('2014-05-01')])->save());

        return [$partido, app(CedulaService::class)->abrir($partido)];
    }
}
