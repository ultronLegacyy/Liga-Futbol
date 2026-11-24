<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\ComunicadoAsamblea;
use App\Models\Jornada;
use App\Models\Jugador;
use App\Models\Sancion;
use App\Services\PortalResultadosService;
use App\Services\ProgramacionPublicaService;
use App\Services\VigenciaSancionService;
use Carbon\CarbonImmutable;
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
            ->groupBy(fn (Sancion $sancion): string => $equipos[$sancion->id] ?? 'Sin equipo')
            ->sortKeys();

        return view('portal.castigados', [
            'porEquipo' => $porEquipo,
            'total' => $boletin->count(),
            'hoy' => Carbon::today(Sancion::ZONA_HORARIA),
            // PA-15: comunicados de asamblea publicados de forma expresa; sin datos de quien los registró
            'comunicados' => ComunicadoAsamblea::query()
                ->publicadosEnPortal()
                ->limit(10)
                ->get(['id', 'titulo', 'contenido', 'publicado_en']),
        ]);
    }

    // HU-077: resultados oficiales, tabla de posiciones y estadísticas de un grupo, sin cuenta (RF-12.3, IU-01)
    public function resultados(Request $request): View
    {
        $grupos = $this->resultados->grupos();
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
        $texto = mb_substr(trim((string) $request->query('equipo', '')), 0, 60);
        $categoriaId = $request->integer('categoria') ?: null;
        $fecha = null;

        $fechaTexto = (string) $request->query('fecha', '');
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

        // Boletín: solo con el nombre del equipo, porque las sanciones vigentes no dependen de la fecha ni de la categoría
        $castigados = collect();
        if ($texto !== '') {
            $boletin = $this->vigencia->obtenerBoletinCastigados();
            $equipos = $this->equiposDeSancionados($boletin);
            $castigados = $boletin
                ->filter(fn (Sancion $sancion): bool => isset($equipos[$sancion->id]) && mb_stripos($equipos[$sancion->id], $texto) !== false)
                ->map(function (Sancion $sancion) use ($equipos): Sancion {
                    $sancion->setAttribute('equipo_publico', $equipos[$sancion->id]);

                    return $sancion;
                })
                ->values();
        }

        $comunicados = $hayFiltros && ($texto !== '' || $fecha !== null)
            ? ComunicadoAsamblea::query()
                ->publicadosEnPortal()
                ->when($fecha !== null, fn ($consulta) => $consulta->whereDate('publicado_en', $fecha?->toDateString()))
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
     * Equipo de cada sancionado: el de la sanción o, si no lo tiene, el del jugador (una sola consulta).
     *
     * @param  \Illuminate\Support\Collection<int, Sancion>  $boletin
     * @return array<int, string>
     */
    private function equiposDeSancionados(\Illuminate\Support\Collection $boletin): array
    {
        $equiposPorPersona = Jugador::query()
            ->whereIn('persona_id', $boletin->pluck('persona_id')->filter()->unique())
            ->with('equipo:id,nombre')
            ->get(['id', 'persona_id', 'equipo_id'])
            ->mapWithKeys(fn (Jugador $jugador): array => [$jugador->persona_id => $jugador->equipo?->nombre]);

        $equipos = [];
        foreach ($boletin as $sancion) {
            $nombre = $sancion->equipo->nombre
                ?? ($sancion->persona_id !== null ? $equiposPorPersona->get($sancion->persona_id) : null);

            if ($nombre !== null) {
                $equipos[$sancion->id] = $nombre;
            }
        }

        return $equipos;
    }
}
