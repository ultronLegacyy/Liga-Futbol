<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cedula\GuardarCedulaFisicaRequest;
use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Models\User;
use App\Services\CapturaCedulaFisicaService;
use App\Services\CedulaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

// HU-037 (RF-08.1): el Capturista transcribe en la sede las cédulas que llegan en papel
class CedulaFisicaController extends Controller
{
    public function __construct(
        private readonly CapturaCedulaFisicaService $captura,
        private readonly CedulaService $cedulas,
    ) {
    }

    // Partidos de hoy o anteriores con la cédula pendiente
    public function index(): View
    {
        Gate::authorize('capturarFisica', CedulaArbitral::class);

        return view('cedulas.fisicas.index', ['partidos' => $this->captura->pendientes()]);
    }

    // Formulario rápido: un GET no abre ni guarda nada
    public function create(Partido $partido): View
    {
        Gate::authorize('capturarFisica', [CedulaArbitral::class, $partido]);

        $partido->load(['local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre', 'jornada.grupo.categoria:id,nombre']);
        $cedula = $partido->cedula()->first();

        if ($cedula?->estaCerrada()) {
            return view('cedulas.fisicas.cerrada', ['partido' => $partido]);
        }

        // La alineación de la cédula, o la plantilla vigente si todavía no se abre
        $filas = $cedula === null
            ? $this->cedulas->plantilla($partido)->map(fn ($jugador) => [
                'jugador_id' => $jugador->id,
                'equipo_id' => $jugador->equipo_id,
                'nombre' => trim($jugador->persona->nombre.' '.$jugador->persona->apellidos),
                'estado' => $jugador->estado_elegibilidad,
                'asistio' => false,
                'numero_camiseta' => $jugador->dorsal,
                'es_capitan' => false,
            ])
            : $cedula->alineaciones()->whereNotNull('jugador_id')->with('jugador.persona:id,nombre,apellidos')->orderBy('numero_camiseta')->get()->map(fn ($fila) => [
                'jugador_id' => $fila->jugador_id,
                'equipo_id' => $fila->equipo_id,
                'nombre' => trim(($fila->jugador?->persona->nombre ?? '').' '.($fila->jugador?->persona->apellidos ?? '')),
                'estado' => $fila->jugador?->estado_elegibilidad,
                'asistio' => (bool) $fila->asistio,
                'numero_camiseta' => $fila->numero_camiseta,
                'es_capitan' => (bool) $fila->es_capitan,
            ]);

        $cedula?->load([
            'eventos' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('minuto')->orderBy('id'),
            'incidencias' => fn ($consulta) => $consulta->with('equipo:id,nombre')->orderBy('minuto')->orderBy('id'),
        ]);

        return view('cedulas.fisicas.create', [
            'partido' => $partido,
            'cedula' => $cedula,
            'equipos' => [
                (int) $partido->equipo_local_id => (string) $partido->local?->nombre,
                (int) $partido->equipo_visitante_id => (string) $partido->visitante?->nombre,
            ],
            'filas' => $filas->groupBy('equipo_id'),
            'claveEnvio' => (string) Str::uuid(),
            'filasEventos' => GuardarCedulaFisicaRequest::FILAS_EVENTOS,
            'filasIncidencias' => GuardarCedulaFisicaRequest::FILAS_INCIDENCIAS,
        ]);
    }

    public function store(GuardarCedulaFisicaRequest $request, Partido $partido): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $resultado = $this->captura->guardar($partido, $request->datos(), $request->clave(), $usuario);

        return redirect()->route('cedulas.show', $partido)
            ->with('success', 'Cédula en papel capturada. Revisa los datos antes de cerrarla.')
            ->with('hallazgos', $resultado['hallazgos']);
    }
}
