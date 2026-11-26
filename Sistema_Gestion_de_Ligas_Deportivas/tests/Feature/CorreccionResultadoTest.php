<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\TablaPosicion;
use App\Models\TablaPosicionJornada;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaGrupoConResultados;
use Tests\TestCase;

// HU-039 (SCRUM-51, RF-08.3, RNF-33): corregir un resultado ya validado con motivo, auditoría y recálculo
class CorreccionResultadoTest extends TestCase
{
    use CreaGrupoConResultados;
    use DatabaseTransactions;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->administrador = $this->usuario('Administrador General');
    }

    // CA1: el Administrador corrige con motivo y la bitácora guarda el marcador anterior, el nuevo y el motivo
    public function test_corregir_exige_motivo_y_queda_en_la_bitacora(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B']);
        $cedula = $this->resultado($grupo, 1, $e['A'], 2, 1, $e['B']);
        $ultimo = (int) AuditoriaLog::query()->max('id');

        $this->actingAs($this->administrador)
            ->put(route('resultados.corregir.update', $cedula->id), ['goles_local' => 1, 'goles_visitante' => 1, 'motivo' => 'El árbitro anotó un gol que fue anulado'])
            ->assertRedirect(route('resultados.corregir.edit', $cedula->id))
            ->assertSessionHas('success');

        $cedula->refresh();
        $this->assertSame([1, 1], [$cedula->goles_local, $cedula->goles_visitante]);

        $log = AuditoriaLog::query()->where('id', '>', $ultimo)->where('tabla_afectada', 'cedulas_arbitrales')->sole();
        $this->assertSame('actualizar', $log->accion);
        $this->assertSame($this->administrador->id, $log->usuario_id);
        $this->assertSame('Corrección del resultado del partido '.$cedula->partido_id.': El árbitro anotó un gol que fue anulado', $log->motivo);
        $this->assertSame(2, $log->valores_anteriores['goles_local'] ?? null);
        $this->assertSame(1, $log->valores_nuevos['goles_local'] ?? null);
    }

    // CA1: sin motivo, o con datos inválidos, no se corrige y los mensajes salen en español
    public function test_sin_motivo_no_se_corrige(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B']);
        $cedula = $this->resultado($grupo, 1, $e['A'], 2, 1, $e['B']);
        $ruta = route('resultados.corregir.update', $cedula->id);
        $this->actingAs($this->administrador);

        $this->putJson($ruta, ['goles_local' => 1, 'goles_visitante' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.motivo', ['Escribe el motivo de la corrección; quedará en la bitácora.']);
        $this->putJson($ruta, ['goles_local' => 'dos', 'goles_visitante' => -1, 'motivo' => ['x']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.goles_local', ['Los goles deben ser un número entero.'])
            ->assertJsonPath('errors.goles_visitante', ['Los goles no pueden ser negativos.'])
            ->assertJsonPath('errors.motivo', ['El motivo de la corrección no es válido.']);
        $this->putJson($ruta, ['goles_local' => 2, 'goles_visitante' => 1, 'motivo' => 'Revisión sin cambios'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.goles_local.0', 'El marcador es el mismo que el oficial: no hay nada que corregir.');

        $this->assertSame([2, 1], [$cedula->refresh()->goles_local, $cedula->goles_visitante]);
    }

    // CA2: la tabla del grupo y la copia de la jornada se recalculan con el marcador corregido
    public function test_la_tabla_y_la_copia_de_la_jornada_se_recalculan(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B']);
        $cedula = $this->resultado($grupo, 1, $e['A'], 2, 1, $e['B']);
        $this->assertSame(3, TablaPosicion::query()->where('equipo_id', $e['A']->id)->value('puntos'));
        $this->assertSame(3, TablaPosicionJornada::query()->where('equipo_id', $e['A']->id)->value('puntos'));

        $this->actingAs($this->administrador)
            ->put(route('resultados.corregir.update', $cedula->id), ['goles_local' => 0, 'goles_visitante' => 1, 'motivo' => 'Se capturó al revés el marcador']);

        $this->assertSame(0, TablaPosicion::query()->where('equipo_id', $e['A']->id)->value('puntos'));
        $this->assertSame(3, TablaPosicion::query()->where('equipo_id', $e['B']->id)->value('puntos'));
        $this->assertSame(1, TablaPosicion::query()->where('equipo_id', $e['B']->id)->value('posicion'));
        $this->assertSame(3, TablaPosicionJornada::query()->where('equipo_id', $e['B']->id)->value('puntos'));
        $this->assertSame(1, TablaPosicionJornada::query()->where('equipo_id', $e['A']->id)->count());
    }

    // CA3: quien capturó o cerró la cédula no la corrige, aunque sea Administrador; otros roles tampoco
    public function test_quien_no_esta_autorizado_no_corrige(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B']);
        $cedula = $this->resultado($grupo, 1, $e['A'], 2, 1, $e['B']);
        $datos = ['goles_local' => 0, 'goles_visitante' => 0, 'motivo' => 'Intento de corrección no permitido'];

        $cedula->forceFill(['capturada_por_id' => $this->administrador->id])->save();
        $this->actingAs($this->administrador)
            ->put(route('resultados.corregir.update', $cedula->id), $datos)
            ->assertForbidden()
            ->assertSee('Quien capturó o cerró la cédula no puede corregir su resultado');
        $this->actingAs($this->administrador)->get(route('resultados.corregir.edit', $cedula->id))->assertForbidden();

        foreach (['Capturista', 'Programador de Partidos', 'Secretario'] as $rol) {
            $this->actingAs($this->usuario($rol))
                ->put(route('resultados.corregir.update', $cedula->id), $datos)
                ->assertForbidden()
                ->assertSee('Solo el Administrador General puede corregir un resultado ya validado.');
        }

        $this->assertSame([2, 1], [$cedula->refresh()->goles_local, $cedula->goles_visitante]);
    }

    // Un resultado que todavía no es oficial no se corrige por esta vía
    public function test_un_resultado_sin_validar_no_se_corrige(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['A', 'B']);
        $cedula = $this->resultado($grupo, 1, $e['A'], 2, 1, $e['B'], validar: false);

        $this->actingAs($this->administrador)
            ->from(route('resultados.index'))
            ->put(route('resultados.corregir.update', $cedula->id), ['goles_local' => 0, 'goles_visitante' => 0, 'motivo' => 'Corrección antes de validar'])
            ->assertSessionHasErrors('goles_local');

        $this->assertSame([2, 1], [$cedula->refresh()->goles_local, $cedula->goles_visitante]);
    }

    // La pantalla de corrección muestra el resultado oficial y las correcciones anteriores; el listado enlaza a ella
    public function test_la_pantalla_muestra_las_correcciones_anteriores(): void
    {
        [$grupo, $e] = $this->grupoConEquipos(['Leones', 'Tigres']);
        $cedula = $this->resultado($grupo, 1, $e['Leones'], 2, 1, $e['Tigres']);
        $this->actingAs($this->administrador)
            ->put(route('resultados.corregir.update', $cedula->id), ['goles_local' => 3, 'goles_visitante' => 1, 'motivo' => 'Faltó anotar un gol del local']);

        $this->actingAs($this->administrador)
            ->get(route('resultados.corregir.edit', $cedula->id))
            ->assertOk()
            ->assertSee('Leones 3 - 1 Tigres')
            ->assertSeeInOrder(['Correcciones anteriores', '2 - 1', '3 - 1', 'Faltó anotar un gol del local']);

        $this->actingAs($this->administrador)
            ->get(route('resultados.index'))
            ->assertSee('href="'.route('resultados.corregir.edit', $cedula->id).'"', false);
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
