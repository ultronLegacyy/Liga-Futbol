<?php

namespace App\Http\Controllers;

use App\Models\ComunicadoAsamblea;
use App\Models\Jugador;
use App\Models\Sancion;
use App\Services\PortalResultadosService;
use App\Services\ProgramacionPublicaService;
use App\Services\VigenciaSancionService;
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

        // Equipo de cada sancionado: el de la sanción o, si no lo tiene, el de su inscripción vigente (una sola
        // consulta). Se descartan las bajas, como en el boletín del Delegado, y se ordena por id para quedarse con
        // la inscripción más reciente sin depender del orden en que la base devuelva las filas.
        $equiposPorPersona = Jugador::query()
            ->whereIn('persona_id', $boletin->pluck('persona_id')->filter()->unique())
            ->where('estado_elegibilidad', '!=', 'Baja')
            ->orderBy('id')
            ->with('equipo:id,nombre')
            ->get(['id', 'persona_id', 'equipo_id'])
            ->mapWithKeys(fn (Jugador $jugador): array => [$jugador->persona_id => $jugador->equipo?->nombre]);

        $porEquipo = $boletin
            ->groupBy(fn (Sancion $sancion): string => $sancion->equipo->nombre
                ?? ($sancion->persona_id !== null ? $equiposPorPersona->get($sancion->persona_id) : null)
                ?? 'Sin equipo')
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
}
