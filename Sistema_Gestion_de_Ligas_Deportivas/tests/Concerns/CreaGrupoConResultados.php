<?php

namespace Tests\Concerns;

use App\Models\Categoria;
use App\Models\CedulaArbitral;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use App\Models\ReglaTemporada;
use App\Models\Temporada;
use App\Models\User;
use App\Services\ValidacionResultadoService;
use Illuminate\Support\Facades\Hash;

// Grupo con reglas de puntos y resultados capturados para las pruebas de HU-040, HU-041, HU-043 y HU-055
trait CreaGrupoConResultados
{
    private int $numeroFixture = 0;

    private ?User $validador = null;

    /**
     * Grupo de una temporada nueva con sus reglas (versión 1) y un equipo por nombre.
     *
     * @param  list<string>  $nombres
     * @param  array{0: int, 1: int, 2: int}  $puntos  victoria, empate y derrota
     * @param  list<string>  $desempates
     * @return array{0: Grupo, 1: array<string, Equipo>}
     */
    private function grupoConEquipos(array $nombres = ['A', 'B', 'C', 'D'], array $puntos = [3, 1, 0], array $desempates = ['diferencia_goles', 'goles_favor'], bool $conReglas = true): array
    {
        $this->numeroFixture++;
        $temporada = Temporada::factory()->activa()->create();
        $grupo = Grupo::factory()->create(['categoria_id' => Categoria::factory()->create(['temporada_id' => $temporada->id])->id]);

        if ($conReglas) {
            $this->reglas($temporada, $puntos, $desempates);
        }

        $equipos = [];
        foreach ($nombres as $nombre) {
            $equipos[$nombre] = Equipo::create([
                'club_id' => Club::create(['nombre' => 'Club '.$nombre.' '.$this->numeroFixture.'-'.$grupo->id])->id,
                'grupo_id' => $grupo->id,
                'nombre' => $nombre,
            ]);
        }

        return [$grupo, $equipos];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $puntos
     * @param  list<string>  $desempates
     */
    private function reglas(Temporada $temporada, array $puntos, array $desempates, int $version = 1): ReglaTemporada
    {
        return ReglaTemporada::create([
            'temporada_id' => $temporada->id,
            'version' => $version,
            'reglas' => [
                'cuotas' => ['inscripcion' => 1000],
                'fechas_limite' => ['registro' => '2026-12-01'],
                'clasificacion' => ['puntos_victoria' => $puntos[0], 'puntos_empate' => $puntos[1], 'puntos_derrota' => $puntos[2]],
                'desempates' => $desempates,
                'ascenso_descenso' => ['equipos_ascienden' => 1, 'equipos_descienden' => 1],
            ],
            'motivo' => 'Reglas de prueba',
        ]);
    }

    // Partido de la jornada con su cédula cerrada; si $validar, se valida como lo haría el Programador de Partidos
    private function resultado(Grupo $grupo, int $numeroJornada, Equipo $local, int $golesLocal, int $golesVisitante, Equipo $visitante, bool $validar = true): CedulaArbitral
    {
        $jornada = Jornada::query()->firstOrCreate(
            ['grupo_id' => $grupo->id, 'numero_jornada' => $numeroJornada],
            ['fecha_inicio' => '2026-11-14', 'fecha_fin' => '2026-11-15'],
        );

        $partido = Partido::create([
            'jornada_id' => $jornada->id,
            'equipo_local_id' => $local->id,
            'equipo_visitante_id' => $visitante->id,
            'estado' => 'programado',
        ]);

        $cedula = CedulaArbitral::create([
            'partido_id' => $partido->id,
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

    private function validador(): User
    {
        if ($this->validador === null) {
            $this->numeroFixture++;
            $this->validador = User::create([
                'name' => 'Programador de prueba',
                'email' => 'programador'.$this->numeroFixture.'-'.uniqid().'@liga.test',
                'password' => Hash::make('secreto123'),
                'estatus' => 'activo',
                'rol' => 'Consultor',
            ]);
        }

        return $this->validador;
    }
}
