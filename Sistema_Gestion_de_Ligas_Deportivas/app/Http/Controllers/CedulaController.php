<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cedula\AbrirCedulaRequest;
use App\Http\Requests\Cedula\GuardarAlineacionRequest;
use App\Http\Requests\Cedula\GuardarMarcadorRequest;
use App\Http\Requests\Cedula\RegistrarEventoRequest;
use App\Http\Requests\Cedula\RegistrarIncidenciaRequest;
use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Models\User;
use App\Services\CedulaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Cédula arbitral del partido (HU-036): consulta, apertura por el árbitro asignado y captura desde el celular
class CedulaController extends Controller
{
    public function __construct(private readonly CedulaService $cedulas)
    {
    }

    // Partidos asignados al árbitro que inició sesión, con el enlace a cada cédula
    public function misPartidos(Request $request): View
    {
        Gate::authorize('misPartidos', CedulaArbitral::class);

        /** @var User $usuario */
        $usuario = $request->user();

        return view('cedulas.mis-partidos', ['partidos' => $this->cedulas->partidosAsignados($usuario)]);
    }

    /**
     * Un GET no crea nada. Quien puede capturar en una cédula abierta ve la pantalla de captura;
     * los demás, la consulta, con la plantilla vigente mientras la cédula no esté abierta.
     */
    public function show(Partido $partido): View
    {
        Gate::authorize('ver', [CedulaArbitral::class, $partido]);

        $cedula = $partido->cedula()->first();
        $estadoPermitido = in_array($partido->estado, CedulaArbitral::ESTADOS_PARTIDO_CAPTURA, true);

        $partido->load(['local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre', 'arbitro.persona:id,nombre,apellidos']);

        // Sin la CURP: de cada persona solo el nombre
        $cedula?->load([
            'alineaciones' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('equipo_id')->orderBy('numero_camiseta'),
            'eventos' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('minuto')->orderBy('id'),
            'incidencias' => fn ($consulta) => $consulta->with('equipo:id,nombre')->orderBy('minuto')->orderBy('id'),
        ]);

        if ($cedula !== null && ! $cedula->estaCerrada() && $estadoPermitido && Gate::allows('capturar', [CedulaArbitral::class, $partido])) {
            return view('cedulas.captura', [
                'partido' => $partido,
                'cedula' => $cedula,
                'jugadoresNuevos' => Gate::allows('abrir', [CedulaArbitral::class, $partido]) ? $this->cedulas->jugadoresNuevos($cedula, $partido) : 0,
            ]);
        }

        return view('cedulas.show', [
            'partido' => $partido,
            'cedula' => $cedula,
            'plantilla' => $cedula === null ? $this->cedulas->plantilla($partido) : null,
            'estadoPermitido' => $estadoPermitido,
            'puedeAbrir' => $estadoPermitido && Gate::allows('abrir', [CedulaArbitral::class, $partido]) && ($cedula === null || ! $cedula->estaCerrada()),
        ]);
    }

    // Abre la cédula o, si ya está abierta, suma a los jugadores nuevos de la plantilla
    public function abrir(AbrirCedulaRequest $request, Partido $partido): RedirectResponse
    {
        $nueva = $partido->cedula()->doesntExist();
        $this->cedulas->abrir($partido);

        return redirect()->route('cedulas.show', $partido)
            ->with('success', $nueva ? 'Cédula abierta: ya puedes capturar el partido.' : 'La alineación quedó al día con la plantilla.');
    }

    public function alineacion(GuardarAlineacionRequest $request, Partido $partido, string $jugador): JsonResponse
    {
        $fila = $this->cedulas->guardarAlineacion($this->cedulas->abierta($partido), (int) $jugador, $request->datos(), $request->clave());

        return response()->json([
            'jugador_id' => $fila->jugador_id,
            'asistio' => $fila->asistio,
            'numero_camiseta' => $fila->numero_camiseta,
            'es_capitan' => $fila->es_capitan,
        ]);
    }

    public function marcador(GuardarMarcadorRequest $request, Partido $partido): JsonResponse
    {
        $cedula = $this->cedulas->guardarMarcador(
            $this->cedulas->abierta($partido),
            (int) $request->validated('goles_local'),
            (int) $request->validated('goles_visitante'),
            $request->clave()
        );

        return response()->json(['goles_local' => $cedula->goles_local, 'goles_visitante' => $cedula->goles_visitante]);
    }

    // Una clave repetida responde igual que la primera vez (RNF-21)
    public function evento(RegistrarEventoRequest $request, Partido $partido): JsonResponse
    {
        $evento = $this->cedulas->registrarEvento($this->cedulas->abierta($partido), $request->datos(), $request->clave());

        return response()->json([
            'id' => $evento->id,
            'jugador_id' => $evento->jugador_id,
            'tipo' => $evento->tipo,
            'autogol' => (bool) $evento->autogol,
            'minuto' => $evento->minuto,
        ]);
    }

    public function incidencia(RegistrarIncidenciaRequest $request, Partido $partido): JsonResponse
    {
        $incidencia = $this->cedulas->registrarIncidencia($this->cedulas->abierta($partido), $request->datos(), $request->clave());

        return response()->json([
            'id' => $incidencia->id,
            'equipo_id' => $incidencia->equipo_id,
            'minuto' => $incidencia->minuto,
            'descripcion' => $incidencia->descripcion,
        ]);
    }
}
