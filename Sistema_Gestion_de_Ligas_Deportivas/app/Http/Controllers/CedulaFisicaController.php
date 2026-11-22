<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cedula\CerrarCedulaFisicaRequest;
use App\Http\Requests\Cedula\GuardarCedulaFisicaRequest;
use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Models\User;
use App\Services\CapturaCedulaFisicaService;
use App\Services\CedulaService;
use App\Services\CierreCedulaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// HU-037 (RF-08.1): el Capturista transcribe en la sede las cédulas que llegan en papel
class CedulaFisicaController extends Controller
{
    public function __construct(
        private readonly CapturaCedulaFisicaService $captura,
        private readonly CedulaService $cedulas,
        private readonly CierreCedulaService $cierre,
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
        Gate::authorize('capturarFisica', CedulaArbitral::class);

        $partido->load(['local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre', 'jornada.grupo.categoria:id,nombre']);
        $cedula = $partido->cedula()->first();
        $motivo = $this->captura->motivoNoDisponible($partido, $cedula);

        // Cédula cerrada, partido fuera de captura o cédula ya capturada en campo: un aviso, sin formulario
        if ($motivo !== null) {
            return view('cedulas.fisicas.no-disponible', ['partido' => $partido, 'motivo' => $motivo]);
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

        // Obligatorio 1 (#89): el mismo formulario reenviado, por ejemplo con «Atrás», no se vuelve a aplicar
        if ($resultado['ya_guardado']) {
            return redirect()->route('cedulas.show', $partido)
                ->with('warning', 'Este formulario ya se había guardado y no se aplicó otra vez. Para corregir algo, abre de nuevo «Capturar desde la cédula en papel».');
        }

        return redirect()->route('cedulas.fisica.create', $partido)
            ->with('success', 'Cédula en papel capturada. Revisa los datos y, si coinciden con el papel, ciérrala abajo.')
            ->with('hallazgos', $resultado['hallazgos']);
    }

    // Respuesta 50 del PO: el Capturista cierra la cédula transcrita con el escaneo de la cédula en papel firmada
    public function cerrar(CerrarCedulaFisicaRequest $request, Partido $partido): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        try {
            $this->cierre->cerrarDesdePapel($this->cedulas->abierta($partido), $request->escaneo(), $usuario);
        } catch (ValidationException $error) {
            // Los errores del cierre se muestran en su sección, no como errores de la captura
            throw $error->errorBag('cierre');
        }

        return redirect()->route('cedulas.show', $partido)->with('success', 'Cédula en papel cerrada: ya no se puede modificar. El partido quedó finalizado.');
    }

    // Escaneo de la cédula en papel: disco privado, sin caché y sin indexar (RNF-30)
    public function escaneo(Partido $partido): StreamedResponse
    {
        Gate::authorize('ver', [CedulaArbitral::class, $partido]);

        $cedula = $partido->cedula()->firstOrFail();
        $ruta = $this->cierre->rutaDeEscaneo($cedula);
        abort_if($ruta === null, 404);

        return Storage::disk('local')->response($ruta, 'cedula-partido-'.$partido->id.'.'.pathinfo($ruta, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
