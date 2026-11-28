<?php

namespace Database\Seeders;

use App\Models\Arbitro;
use App\Models\Asamblea;
use App\Models\Cancha;
use App\Models\CatalogoArticulo;
use App\Models\Categoria;
use App\Models\CedulaArbitral;
use App\Models\Club;
use App\Models\ComunicadoAsamblea;
use App\Models\Equipo;
use App\Models\EventoPartido;
use App\Models\ExpedienteDisciplinario;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Persona;
use App\Models\ReglaTemporada;
use App\Models\Sancion;
use App\Models\Temporada;
use App\Models\User;
use App\Services\GestionJugadorService;
use App\Services\TablaPosicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Liga de demostración para revisar el portal y los paneles por rol con datos que parecen reales (HAB-S7, S7-T07):
 * 3 categorías con 6 equipos y 8 jugadores cada uno, 4 jornadas jugadas con cédula cerrada y validada (tablas, goleo
 * y tarjetas), la jornada 5 publicada esta semana con árbitro y cancha, castigados publicados en el boletín y
 * comunicados de asamblea publicados en el portal. Un equipo de Primera Fuerza pertenece al Club Deportivo Morelia
 * para que «Mi club» tenga contenido, y el usuario árbitro de prueba queda asignado a partidos de esta semana.
 *
 * Uso, después de DatabaseSeeder: php artisan db:seed --class=LigaDemoSeeder
 * Se puede ejecutar varias veces; también retira la demostración anterior del portal («Local 1 vs. Visitante 1»).
 *
 * SOLO PARA BASES DE REVISIÓN O DESARROLLO. No se ejecuta en la base real de la liga:
 * - crea una temporada «activa» más (si DatabaseSeeder ya creó una, quedan dos activas);
 * - borra en masa la demostración anterior con ->delete() sobre consultas, sin auditoría;
 * - usa factories, que dependen de fakerphp/faker (require-dev): no corre en un servidor instalado con --no-dev.
 */
class LigaDemoSeeder extends Seeder
{
    public const NOMBRE = 'Temporada Apertura 2026 (demostración)';

    /** @var list<array{0: string, 1: string, 2: string, 3: list<string>}> */
    private const CATEGORIAS = [
        ['Primera Fuerza', 'Libre', 'Grupo A', ['Halcones Tarímbaro', 'Real Quinceo', 'Deportivo Charo', 'Águilas Prados Verdes', 'Estrellas Ocolusen', 'Atlético Morelia']],
        ['Femenil', 'Femenil', 'Grupo Único', ['Estrellas FC Femenil', 'Panteras Santa María', 'Lobas Tenencia Morelos', 'Venadas Jesús del Monte', 'Itzícuaro Femenil', 'Gacelas Capula']],
        ['Veteranos', 'Veteranos', 'Grupo B', ['Unión Capula', 'Tigres Santa María', 'Pumas Norte', 'Venados Ocolusen', 'Lobos Tenencia', 'Real Chiquimitío']],
    ];

    private const NOMBRES = ['Brandon', 'Iván', 'Diego', 'Óscar', 'Luis', 'Jesús', 'Miguel', 'Carlos', 'Jorge', 'Ricardo', 'Emilio', 'Fernando', 'Alan', 'Kevin', 'Ulises', 'Héctor'];

    private const NOMBRES_FEMENIL = ['Karla', 'Fernanda', 'Daniela', 'Mariana', 'Ximena', 'Valeria', 'Andrea', 'Paola', 'Itzel', 'Lucía', 'Renata', 'Camila', 'Sofía', 'Regina', 'Alejandra', 'Brenda'];

    private const APELLIDOS = ['Ayala', 'Medina', 'Cázares', 'Ponce', 'Ruiz', 'Villa', 'Zavala', 'Guzmán', 'Pérez', 'Torres', 'Gaona', 'Calderón', 'Bautista', 'Sosa', 'Herrera', 'Rangel', 'Mora', 'Luna'];

    private const CANCHAS = ['Unidad Deportiva Morelos · Cancha 1', 'Unidad Deportiva Morelos · Cancha 2', 'Campo Venustiano Carranza', 'Campo Tenencia Morelos'];

    private int $consecutivo = 0;

    public function run(): void
    {
        if (Temporada::where('nombre', self::NOMBRE)->exists()) {
            return;
        }

        // Resultados siempre iguales para que las capturas y la revisión coincidan
        mt_srand(2026);

        DB::transaction(function (): void {
            $this->retirarDemostracionAnterior();

            $lunes = CarbonImmutable::parse(GestionJugadorService::hoy())->startOfWeek(CarbonImmutable::MONDAY);
            $admin = User::query()->where('email', 'admin@ligafutbol.com')->first() ?? User::query()->orderBy('id')->first();
            $tribunal = User::query()->where('email', 'tribunal@ligafutbol.com')->first() ?? $admin;
            $secretario = User::query()->where('email', 'secretario@ligafutbol.com')->first() ?? $admin;
            $clubMorelia = Club::query()->where('nombre', 'Club Deportivo Morelia')->first();

            $temporada = Temporada::factory()->activa()->create(['nombre' => self::NOMBRE]);
            ReglaTemporada::create([
                'temporada_id' => $temporada->id,
                'version' => 1,
                'reglas' => [
                    'clasificacion' => ['puntos_victoria' => 3, 'puntos_empate' => 1, 'puntos_derrota' => 0],
                    'desempates' => ['diferencia_goles', 'goles_favor'],
                ],
                'usuario_id' => $admin?->id,
                'motivo' => 'Reglas de la liga de demostración',
            ]);

            $canchas = collect(self::CANCHAS)->map(fn (string $nombre): Cancha => Cancha::query()->firstOrCreate(
                ['nombre' => $nombre],
                ['direccion_ubicacion' => 'Morelia, Michoacán', 'capacidad_partidos_consecutivos' => 4],
            ))->values();
            $arbitros = $this->arbitros();

            $grupos = [];
            $rojas = [];
            foreach (self::CATEGORIAS as $indiceCategoria => [$nombreCategoria, $rama, $nombreGrupo, $equipos]) {
                $categoria = Categoria::factory()->create(['temporada_id' => $temporada->id, 'nombre' => $nombreCategoria, 'rama' => $rama]);
                $grupo = Grupo::factory()->create(['categoria_id' => $categoria->id, 'temporada_id' => $temporada->id, 'nombre' => $nombreGrupo]);
                $grupos[] = $grupo;

                $equiposDelGrupo = collect($equipos)->map(fn (string $nombre, int $indice): Equipo => $this->equipo(
                    $grupo,
                    $nombre,
                    // El último equipo de Primera Fuerza es del Club Deportivo Morelia («Mi club»)
                    $indiceCategoria === 0 && $indice === 5 ? $clubMorelia : null,
                    $rama === 'Femenil',
                ))->values();

                foreach ($this->rondas($equiposDelGrupo->all()) as $numero => $partidos) {
                    $jornadaNumero = $numero + 1;
                    $jugada = $jornadaNumero <= 4;
                    // Jornadas 1 a 4 en las cuatro semanas anteriores; la 5 es la de esta semana
                    $sabado = $lunes->subWeeks(5 - $jornadaNumero)->addDays(5);
                    $jornada = Jornada::create([
                        'grupo_id' => $grupo->id,
                        'numero_jornada' => $jornadaNumero,
                        'fecha_inicio' => $sabado->toDateString(),
                        'fecha_fin' => $sabado->addDay()->toDateString(),
                        'estado' => Jornada::ESTADO_PUBLICADA,
                        'fecha_publicacion' => $sabado->subDays(4),
                    ]);

                    foreach ($partidos as $orden => [$local, $visitante]) {
                        $partido = Partido::create([
                            'jornada_id' => $jornada->id,
                            'equipo_local_id' => $local->id,
                            'equipo_visitante_id' => $visitante->id,
                            'cancha_id' => $canchas[($indiceCategoria + $orden) % $canchas->count()]->id,
                            'arbitro_id' => $arbitros[($indiceCategoria + $orden) % count($arbitros)]->id,
                            // Primera Fuerza y Femenil el sábado, Veteranos el domingo
                            'fecha_partido' => ($indiceCategoria === 2 ? $sabado->addDay() : $sabado)->toDateString(),
                            'hora_partido' => ['09:00:00', '11:00:00', '13:00:00'][$orden],
                            'estado' => $jugada ? Partido::ESTADO_FINALIZADO : Partido::ESTADO_CONFIRMADO,
                        ]);

                        if ($jugada) {
                            $rojas = [...$rojas, ...$this->resultado($partido, $local, $visitante, $admin)];
                        }
                    }
                }
            }

            foreach ($grupos as $grupo) {
                app(TablaPosicionesService::class)->recalcular($grupo);
            }

            $this->castigados($rojas, $tribunal);
            $this->comunicados($secretario);
        });
    }

    // La demostración anterior solo tenía programación de prueba («Local 1 vs. Visitante 1»), sin cédulas ni jugadores
    private function retirarDemostracionAnterior(): void
    {
        $anterior = Temporada::query()->where('nombre', PortalProgramacionDemoSeeder::NOMBRE)->first();
        if ($anterior === null) {
            return;
        }

        $grupos = Grupo::query()->where('temporada_id', $anterior->id)->pluck('id');
        $equipos = Equipo::query()->whereIn('grupo_id', $grupos)->get(['id', 'club_id']);
        Partido::query()->whereIn('jornada_id', Jornada::query()->whereIn('grupo_id', $grupos)->select('id'))->delete();
        Jornada::query()->whereIn('grupo_id', $grupos)->delete();
        Equipo::query()->whereIn('id', $equipos->pluck('id'))->delete();
        Club::query()->whereIn('id', $equipos->pluck('club_id'))->where('nombre', 'like', '%(demostración)')->delete();
        Grupo::query()->whereIn('id', $grupos)->delete();
        Categoria::query()->where('temporada_id', $anterior->id)->delete();
        $anterior->delete();
    }

    /**
     * Cuatro árbitros; el primero es la persona del usuario árbitro de prueba, para que vea sus partidos.
     *
     * @return list<Arbitro>
     */
    private function arbitros(): array
    {
        $usuario = User::query()->where('email', 'arbitro@ligafutbol.com')->first();
        $arbitros = [];

        foreach ([['Ramón', 'Esquivel Durán'], ['Gerardo', 'Tapia León'], ['Silvia', 'Contreras Ávila'], ['Arturo', 'Mendoza Ríos']] as $indice => [$nombre, $apellidos]) {
            $personaId = $indice === 0 && $usuario?->persona_id !== null
                ? $usuario->persona_id
                : Persona::factory()->create(['nombre' => $nombre, 'apellidos' => $apellidos])->id;

            $arbitros[] = Arbitro::query()->firstOrCreate(['persona_id' => $personaId], ['colegio_pertenencia' => 'Colegio de Árbitros de Morelia', 'estado' => 'Activo']);

            if ($indice === 0 && $usuario !== null && $usuario->persona_id === null) {
                $usuario->forceFill(['persona_id' => $personaId])->save();
            }
        }

        return $arbitros;
    }

    private function equipo(Grupo $grupo, string $nombre, ?Club $club, bool $femenil): Equipo
    {
        $equipo = Equipo::create([
            'club_id' => $club->id ?? Club::create(['nombre' => 'Club '.$nombre])->id,
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
        ]);

        $nombres = $femenil ? self::NOMBRES_FEMENIL : self::NOMBRES;
        for ($dorsal = 1; $dorsal <= 8; $dorsal++) {
            $this->consecutivo++;
            Jugador::create([
                'persona_id' => Persona::factory()->create([
                    'nombre' => $nombres[($this->consecutivo * 7) % count($nombres)],
                    'apellidos' => self::APELLIDOS[($this->consecutivo * 5) % count(self::APELLIDOS)].' '.self::APELLIDOS[($this->consecutivo * 11 + 3) % count(self::APELLIDOS)],
                ])->id,
                'equipo_id' => $equipo->id,
                'dorsal' => $dorsal,
                'estado_elegibilidad' => 'Activo',
            ]);
        }

        return $equipo;
    }

    /**
     * Todos contra todos por el método del círculo: 5 jornadas de 3 partidos con 6 equipos.
     *
     * @param  list<Equipo>  $equipos
     * @return list<list<array{0: Equipo, 1: Equipo}>>
     */
    private function rondas(array $equipos): array
    {
        $fijo = array_shift($equipos);
        $rondas = [];

        for ($ronda = 0; $ronda < 5; $ronda++) {
            $giro = [...array_slice($equipos, $ronda), ...array_slice($equipos, 0, $ronda)];
            $todos = [$fijo, ...$giro];
            $partidos = [];
            for ($i = 0; $i < 3; $i++) {
                $par = [$todos[$i], $todos[5 - $i]];
                $partidos[] = $ronda % 2 === 0 ? $par : [$par[1], $par[0]];
            }
            $rondas[] = $partidos;
        }

        return $rondas;
    }

    /**
     * Cédula cerrada y validada con goles y tarjetas. Devuelve los jugadores expulsados para el boletín.
     *
     * @return list<array{jugador: Jugador, partido: Partido}>
     */
    private function resultado(Partido $partido, Equipo $local, Equipo $visitante, ?User $validador): array
    {
        $goles = [$this->goles(), $this->goles()];
        $cedula = CedulaArbitral::create([
            'partido_id' => $partido->id,
            'estado' => CedulaArbitral::CERRADA,
            'goles_local' => $goles[0],
            'goles_visitante' => $goles[1],
            'informe_arbitral' => 'Partido sin incidentes mayores.',
            'cerrada_en' => CarbonImmutable::parse($partido->fecha_partido)->setTime(15, 0),
            'validada_por_id' => $validador?->id,
            'validada_en' => CarbonImmutable::parse($partido->fecha_partido)->addDays(2)->setTime(10, 0),
        ]);

        $expulsados = [];
        foreach ([[$local, $goles[0]], [$visitante, $goles[1]]] as [$equipo, $total]) {
            $plantilla = Jugador::query()->where('equipo_id', $equipo->id)->orderBy('dorsal')->get();

            for ($gol = 0; $gol < $total; $gol++) {
                // Los delanteros (dorsales 7 y 8) anotan más, para que el goleo tenga líderes
                $anotador = mt_rand(1, 10) <= 6 ? $plantilla[mt_rand(6, 7)] : $plantilla[mt_rand(1, 5)];
                EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $anotador->id, 'tipo' => 'Gol', 'minuto' => mt_rand(3, 88)]);
            }
            for ($tarjeta = 0, $amarillas = mt_rand(0, 2); $tarjeta < $amarillas; $tarjeta++) {
                EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $plantilla[mt_rand(1, 6)]->id, 'tipo' => 'Amarilla', 'minuto' => mt_rand(10, 85)]);
            }
            if (mt_rand(1, 9) === 1) {
                $expulsado = $plantilla[mt_rand(1, 5)];
                EventoPartido::create(['cedula_id' => $cedula->id, 'jugador_id' => $expulsado->id, 'tipo' => 'Roja', 'minuto' => mt_rand(30, 89)]);
                $expulsados[] = ['jugador' => $expulsado, 'partido' => $partido];
            }
        }

        return $expulsados;
    }

    private function goles(): int
    {
        return [0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 4][mt_rand(0, 10)];
    }

    /**
     * Sanciones validadas y publicadas en el boletín (HU-046): las expulsiones de las jornadas jugadas, como mínimo 3.
     *
     * @param  list<array{jugador: Jugador, partido: Partido}>  $rojas
     */
    private function castigados(array $rojas, ?User $tribunal): void
    {
        if (count($rojas) < 3) {
            foreach (Partido::query()->where('estado', Partido::ESTADO_FINALIZADO)->whereHas('jornada.grupo.temporada', fn ($t) => $t->where('nombre', self::NOMBRE))->latest('id')->limit(3 - count($rojas))->get() as $partido) {
                $rojas[] = ['jugador' => Jugador::query()->where('equipo_id', $partido->equipo_local_id)->orderBy('dorsal')->skip(3)->firstOrFail(), 'partido' => $partido];
            }
        }

        $articulo = CatalogoArticulo::query()->firstOrCreate(
            ['codigo' => 'ART-DEMO-12'],
            ['descripcion' => 'Juego brusco grave', 'castigo_base_partidos' => 2],
        );

        foreach ($rojas as $indice => ['jugador' => $jugador, 'partido' => $partido]) {
            $expediente = ExpedienteDisciplinario::create([
                'folio' => 'EXP-DEMO-'.str_pad((string) ($indice + 1), 3, '0', STR_PAD_LEFT),
                'partido_id' => $partido->id,
                'articulo_id' => $articulo->id,
                'titulo' => 'Expulsión en la jornada',
                'descripcion_hechos' => 'Tarjeta roja directa registrada en la cédula arbitral.',
                'origen' => 'cedula_arbitral',
                'estado' => ExpedienteDisciplinario::ESTADO_RESUELTO,
                'fecha_apertura' => CarbonImmutable::parse($partido->fecha_partido)->addDay(),
                'generado_automaticamente' => true,
            ]);

            $suspendidos = $indice % 3 === 0 ? 3 : 2;
            Sancion::create([
                'expediente_id' => $expediente->id,
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $jugador->equipo_id,
                'articulo_id' => $articulo->id,
                'tipo_sancion' => Sancion::TIPO_PARTIDOS,
                'descripcion_sancion' => 'Suspensión por expulsión',
                'partidos_suspendidos' => $suspendidos,
                'partidos_cumplidos' => min(1, $suspendidos - 1),
                'fecha_inicio' => CarbonImmutable::parse($partido->fecha_partido)->addDays(2)->toDateString(),
                'estado' => Sancion::ESTADO_VIGENTE,
                'publicada_en_boletin' => true,
                'validado_por_usuario_id' => $tribunal?->id,
                'fecha_validacion' => CarbonImmutable::parse($partido->fecha_partido)->addDays(2),
                'autorizado_por_usuario_id' => $tribunal?->id,
                'autorizado_por_cargo' => 'Tribunal de Penas',
                'fecha_publicacion' => CarbonImmutable::parse($partido->fecha_partido)->addDays(2),
            ]);
        }
    }

    // PA-15: comunicados de una asamblea celebrada, publicados en el portal por la Secretaría
    private function comunicados(?User $secretario): void
    {
        // Sin usuarios (base vacía) no hay quién registre el comunicado
        if ($secretario === null) {
            return;
        }

        $asamblea = Asamblea::query()->create([
            'tipo' => 'Ordinaria',
            'fecha' => CarbonImmutable::parse(GestionJugadorService::hoy())->subDays(6)->toDateString(),
            'hora' => '19:00',
            'lugar' => 'Sala de juntas de la Liga',
            'orden_del_dia' => 'Calendario de liguilla y reglamento de canchas',
            'estado' => Asamblea::ESTADO_CELEBRADA,
        ]);

        foreach ([
            ['Liguilla de Primera Fuerza', 'Los cuatro primeros lugares del Grupo A al cierre de la jornada 9 disputarán la liguilla. Las fechas se publicarán en la programación.'],
            ['Uso de las canchas de la Unidad Morelos', 'A partir de la jornada 5 los equipos deberán presentarse 30 minutos antes de su partido con credenciales vigentes.'],
        ] as $orden => [$titulo, $contenido]) {
            ComunicadoAsamblea::query()->create([
                'asamblea_id' => $asamblea->id,
                'titulo' => $titulo,
                'contenido' => $contenido,
                'registrado_por_usuario_id' => $secretario->id,
            ])->forceFill(['publicado_en_portal' => true, 'publicado_en' => now()->subDays(5 - $orden)])->save();
        }
    }
}
