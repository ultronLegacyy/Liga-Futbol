<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\CedulaArbitral;
use App\Models\ComunicadoAsamblea;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Sancion;
use App\Services\PortalResultadosService;
use App\Services\ProgramacionPublicaService;
use App\Services\VigenciaSancionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

// Portal público (RF-12.3, IU-01): se consulta sin cuenta y sin privilegios de edición
class PortalController extends Controller
{
    public function __construct(
        private readonly ProgramacionPublicaService $programacion,
        private readonly VigenciaSancionService $vigencia,
        private readonly PortalResultadosService $resultados,
    ) {
    }

    /**
     * Portada del portal (HAB-S7, S7-T03): el prototipo deportivo aprobado por el Product Owner con datos reales.
     * Partidos de la semana (o de la siguiente con partidos), resultados oficiales recientes, tablas por grupo,
     * goleo y cifras de la temporada en curso y número de personas castigadas. Solo lectura y solo lo publicado (RF-12.3, RF-12.4).
     */
    public function inicio(): View
    {
        $lunes = $this->programacion->inicioDeSemana();
        $dias = $this->programacion->semana($lunes);
        if ($dias->isEmpty()) {
            $siguiente = $this->programacion->siguienteSemanaConPartidos($lunes);
            if ($siguiente !== null) {
                $lunes = $siguiente;
                $dias = $this->programacion->semana($lunes);
            }
        }

        $partidos = $dias->flatten(1)->values();
        $marcadores = CedulaArbitral::query()
            ->oficiales()
            ->whereIn('partido_id', $partidos->pluck('id'))
            ->get(['id', 'partido_id', 'goles_local', 'goles_visitante'])
            ->keyBy('partido_id');

        // La portada es de la temporada en curso: las temporadas cerradas no entran en tablas, goleo ni cifras
        $grupos = $this->resultados->grupos(soloTemporadaEnCurso: true)->take(4)->values();
        $boletin = $this->vigencia->obtenerBoletinCastigados();

        // «Próximo»: el primer partido con fecha y hora posteriores a ahora, en la hora de la liga. Un partido de la
        // mañana que sigue «programado» porque su cédula no se ha capturado ya no cuenta como próximo.
        $ahora = CarbonImmutable::now(Sancion::ZONA_HORARIA);
        $proximo = $partidos
            ->filter(fn ($partido): bool => ! in_array($partido->estado, ['finalizado', 'cancelado', 'en juego'], true)
                && $partido->fecha_partido !== null
                // Sin hora cuenta como próximo solo si es de un día posterior a hoy (la tarjeta dice «hora por confirmar»)
                && ($partido->hora_partido === null
                    ? $partido->fecha_partido->toDateString() > $ahora->toDateString()
                    : CarbonImmutable::parse($partido->fecha_partido->toDateString().' '.$partido->hora_partido, Sancion::ZONA_HORARIA)->greaterThan($ahora)))
            ->sortBy(fn ($partido): string => $partido->fecha_partido->toDateString().' '.($partido->hora_partido ?? '23:59:59'))
            ->first();

        return view('portal.inicio', [
            'lunes' => $lunes,
            'partidos' => $partidos,
            'marcadores' => $marcadores,
            'proximo' => $proximo,
            'categorias' => $partidos
                ->map(fn ($partido) => $partido->jornada?->grupo?->categoria)
                ->filter()
                ->unique('id')
                ->sortBy('nombre')
                ->values(),
            'grupos' => $grupos,
            'tablas' => $grupos->mapWithKeys(fn (Grupo $grupo): array => [$grupo->id => $this->resultados->tabla($grupo)->take(5)]),
            'recientes' => $this->resultados->recientes(),
            'goleo' => $this->resultados->goleoTemporadaEnCurso(6),
            // Personas distintas, igual que en /castigados
            'castigados' => $boletin->pluck('persona_id')->filter()->unique()->count(),
            'cifras' => $this->resultados->cifrasTemporadaEnCurso(),
        ]);
    }

    // HU-062: programación semanal de partidos; ?semana=AAAA-MM-DD elige la semana (una fecha no válida muestra la actual)
    public function programacion(Request $request): View
    {
        $semana = $request->query('semana');
        $lunes = $this->programacion->inicioDeSemana(is_string($semana) ? $semana : null);
        $dias = $this->programacion->semana($lunes);

        return view('portal.programacion', [
            'lunes' => $lunes,
            'domingo' => $lunes->addDays(6),
            'dias' => $dias,
            'siguienteConPartidos' => $dias->isEmpty() ? $this->programacion->siguienteSemanaConPartidos($lunes) : null,
            'esSemanaActual' => $lunes->equalTo($this->programacion->inicioDeSemana()),
            'reprogramadosFuera' => $this->programacion->reprogramadosAOtraSemana($lunes),
            'jornadasEnActualizacion' => $this->programacion->jornadasEnActualizacion($lunes),
        ]);
    }

    // HU-078: boletín público de castigados y comunicados oficiales. Solo sanciones validadas por la Comisión, publicadas en el boletín
    // y aún vigentes (RNF-44, HU-046); de cada persona solo se muestran nombre y equipo (RNF-31)
    public function castigados(): View
    {
        $boletin = $this->vigencia->obtenerBoletinCastigados();

        $equipos = $this->equiposDeSancionados($boletin);
        $porEquipo = $boletin
            ->groupBy(fn (Sancion $sancion): string => $equipos[$sancion->id]['nombre'] ?? 'Sin equipo')
            ->sortKeys();

        return view('portal.castigados', [
            'porEquipo' => $porEquipo,
            // Personas distintas: quien tiene dos sanciones cuenta una vez; las sanciones de equipo van aparte
            'personas' => $boletin->pluck('persona_id')->filter()->unique()->count(),
            'equiposSancionados' => $boletin->whereNull('persona_id')->count(),
            'hoy' => Carbon::today(Sancion::ZONA_HORARIA),
            // Decisión del PO sobre PA-15 (25/11/2026): comunicados de asamblea publicados de forma expresa por el
            // Secretario o el Presidente; sin datos de quien los registró
            'comunicados' => ComunicadoAsamblea::query()
                ->publicadosEnPortal()
                ->limit(10)
                ->get(['id', 'titulo', 'contenido', 'publicado_en']),
        ]);
    }

    // HU-077: resultados oficiales, tabla de posiciones y estadísticas de un grupo, sin cuenta (RF-12.3, IU-01)
    public function resultados(Request $request): View
    {
        // Decisión del PO (27/11/2026, pregunta del #108): el portal muestra solo la temporada en curso
        $grupos = $this->resultados->grupos(soloTemporadaEnCurso: true);
        $solicitado = $request->integer('grupo');

        // Un grupo inexistente o sin resultados muestra el primero disponible, sin error
        $grupo = $grupos->firstWhere('id', $solicitado) ?? $grupos->first();

        return view('portal.resultados', [
            'grupos' => $grupos,
            'grupo' => $grupo,
            'tabla' => $grupo !== null ? $this->resultados->tabla($grupo) : collect(),
            'partidos' => $grupo !== null ? $this->resultados->resultados($grupo) : collect(),
            'estadisticas' => $grupo !== null ? $this->resultados->estadisticas($grupo) : ['goleo' => collect(), 'tarjetas' => collect()],
        ]);
    }

    // HU-063 (RF-12.4): buscador del portal por fecha, categoría y equipo sobre lo publicado (programación,
    // resultados oficiales, boletín y comunicados). Formulario GET sin JavaScript
    public function buscar(Request $request): View
    {
        // Un parámetro que no es texto (equipo[]=x, fecha[]=...) se trata como vacío en lugar de dar 500
        $equipoParametro = $request->query('equipo');
        $texto = is_string($equipoParametro) ? mb_substr(trim($equipoParametro), 0, 60) : '';
        $categoriaId = $request->integer('categoria') ?: null;
        $fecha = null;

        $fechaParametro = $request->query('fecha');
        $fechaTexto = is_string($fechaParametro) ? $fechaParametro : '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaTexto) === 1) {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $fechaTexto, Sancion::ZONA_HORARIA) ?: null;
            if ($fecha !== null && $fecha->toDateString() !== $fechaTexto) {
                $fecha = null;
            }
        }

        $categorias = Categoria::query()
            ->whereHas('grupos.jornadas', fn ($jornada) => $jornada->where('estado', Jornada::ESTADO_PUBLICADA))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'rama']);
        if ($categoriaId !== null && ! $categorias->contains('id', $categoriaId)) {
            $categoriaId = null;
        }

        $hayFiltros = $texto !== '' || $categoriaId !== null || $fecha !== null;

        $partidos = $hayFiltros ? $this->programacion->buscar($fecha, $categoriaId, $texto !== '' ? $texto : null) : collect();

        // Boletín vigente de hoy, filtrado por el nombre del equipo y por la categoría del equipo del sancionado
        // (criterio 1: la categoría se respeta sin importar de qué historia venga el contenido). La fecha no lo
        // filtra: siempre es el boletín vigente, como en /castigados.
        $castigados = collect();
        if ($texto !== '' || $categoriaId !== null) {
            $boletin = $this->vigencia->obtenerBoletinCastigados();
            $equipos = $this->equiposDeSancionados($boletin);
            $castigados = $boletin
                ->filter(fn (Sancion $sancion): bool => isset($equipos[$sancion->id])
                    && ($texto === '' || mb_stripos($equipos[$sancion->id]['nombre'], $texto) !== false)
                    && ($categoriaId === null || $equipos[$sancion->id]['categoria_id'] === $categoriaId))
                ->map(function (Sancion $sancion) use ($equipos): Sancion {
                    $sancion->setAttribute('equipo_publico', $equipos[$sancion->id]['nombre']);

                    return $sancion;
                })
                ->values();
        }

        $comunicados = $hayFiltros && ($texto !== '' || $fecha !== null)
            ? ComunicadoAsamblea::query()
                ->publicadosEnPortal()
                // publicado_en está en UTC: el día se toma completo en la hora de la liga y se convierte a UTC
                ->when($fecha !== null, fn ($consulta) => $consulta->whereBetween('publicado_en', [
                    $fecha?->startOfDay()->utc(),
                    $fecha?->endOfDay()->utc(),
                ]))
                ->when($texto !== '', fn ($consulta) => $consulta->where(fn ($q) => $q
                    ->where('titulo', 'like', '%'.addcslashes($texto, '%_\\').'%')
                    ->orWhere('contenido', 'like', '%'.addcslashes($texto, '%_\\').'%')))
                ->limit(10)
                ->get(['id', 'titulo', 'contenido', 'publicado_en'])
            : collect();

        return view('portal.buscar', [
            'categorias' => $categorias,
            'filtros' => ['equipo' => $texto, 'categoria' => $categoriaId, 'fecha' => $fecha?->toDateString()],
            'hayFiltros' => $hayFiltros,
            'partidos' => $partidos,
            'castigados' => $castigados,
            'comunicados' => $comunicados,
        ]);
    }

    /**
     * Equipo de cada sancionado, con su categoría: el de la sanción o, si no lo tiene, el de la inscripción vigente
     * de la persona. Se descartan las bajas, como en el boletín del Delegado (VigenciaSancionService), y se ordena
     * por id para quedarse con la inscripción más reciente sin depender del orden de la base. Una sola consulta.
     *
     * @param  Collection<int, Sancion>  $boletin
     * @return array<int, array{nombre: string, categoria_id: int|null}>
     */
    private function equiposDeSancionados(Collection $boletin): array
    {
        $boletin->loadMissing('equipo:id,nombre,grupo_id', 'equipo.grupo:id,categoria_id');

        $equiposPorPersona = Jugador::query()
            ->whereIn('persona_id', $boletin->pluck('persona_id')->filter()->unique())
            ->where('estado_elegibilidad', '!=', 'Baja')
            ->orderBy('id')
            ->with('equipo:id,nombre,grupo_id', 'equipo.grupo:id,categoria_id')
            ->get(['id', 'persona_id', 'equipo_id'])
            ->mapWithKeys(fn (Jugador $jugador): array => [$jugador->persona_id => $jugador->equipo]);

        $equipos = [];
        foreach ($boletin as $sancion) {
            $equipo = $sancion->equipo ?? ($sancion->persona_id !== null ? $equiposPorPersona->get($sancion->persona_id) : null);

            if ($equipo !== null) {
                $categoria = $equipo->grupo?->categoria_id;
                $equipos[$sancion->id] = ['nombre' => $equipo->nombre, 'categoria_id' => $categoria !== null ? (int) $categoria : null];
            }
        }

        return $equipos;
    }
}
