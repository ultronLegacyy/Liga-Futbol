<?php

namespace App\Http\Controllers;

use App\Models\ComunicadoAsamblea;
use App\Models\Jugador;
use App\Models\Sancion;
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

        // Equipo de cada sancionado: el de la sanción o, si no lo tiene, el del jugador (una sola consulta)
        $equiposPorPersona = Jugador::query()
            ->whereIn('persona_id', $boletin->pluck('persona_id')->filter()->unique())
            ->with('equipo:id,nombre')
            ->get(['id', 'persona_id', 'equipo_id'])
            ->mapWithKeys(fn (Jugador $jugador): array => [$jugador->persona_id => $jugador->equipo?->nombre]);

        $porEquipo = $boletin
            ->groupBy(fn (Sancion $sancion): string => $sancion->equipo?->nombre
                ?? ($sancion->persona_id !== null ? $equiposPorPersona->get($sancion->persona_id) : null)
                ?? 'Sin equipo')
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
}
