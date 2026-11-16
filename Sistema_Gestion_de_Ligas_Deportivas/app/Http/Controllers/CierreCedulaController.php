<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cedula\CerrarCedulaRequest;
use App\Http\Requests\Cedula\FirmarCedulaRequest;
use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Models\User;
use App\Services\CedulaService;
use App\Services\CierreCedulaService;
use App\Services\ValidacionCedulaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Firmas de los capitanes y cierre de la cédula (HU-075, RF-08.1): resumen para confirmar, firmas y cierre
class CierreCedulaController extends Controller
{
    public function __construct(
        private readonly CedulaService $cedulas,
        private readonly CierreCedulaService $cierre,
        private readonly ValidacionCedulaService $validacion,
    ) {
    }

    // Resumen para confirmar antes de cerrar (criterio 4): datos, validaciones y firmas
    public function resumen(Partido $partido): View|RedirectResponse
    {
        Gate::authorize('cerrar', [CedulaArbitral::class, $partido]);

        $cedula = $partido->cedula()->first();

        if ($cedula === null || $cedula->estaCerrada()) {
            return redirect()->route('cedulas.show', $partido);
        }

        $partido->load(['local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre']);
        $cedula->load([
            'alineaciones' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('equipo_id')->orderBy('numero_camiseta'),
            'eventos' => fn ($consulta) => $consulta->with('jugador.persona:id,nombre,apellidos')->orderBy('minuto')->orderBy('id'),
            'incidencias' => fn ($consulta) => $consulta->with('equipo:id,nombre')->orderBy('minuto')->orderBy('id'),
        ]);

        $firmas = [];

        foreach (CierreCedulaService::LADOS as $lado) {
            $equipoId = $lado === 'local' ? $partido->equipo_local_id : $partido->equipo_visitante_id;
            $firmas[$lado] = [
                'equipo' => $lado === 'local' ? $partido->local?->nombre : $partido->visitante?->nombre,
                'capitan' => $cedula->alineaciones->first(fn ($fila) => (int) $fila->equipo_id === (int) $equipoId && $fila->es_capitan && $fila->jugador_id !== null),
                'firmada' => $this->cierre->tieneFirma($cedula, $lado),
            ];
        }

        return view('cedulas.cierre', [
            'partido' => $partido,
            'cedula' => $cedula,
            'firmas' => $firmas,
            'hallazgos' => $this->validacion->validar($cedula),
        ]);
    }

    public function firmar(FirmarCedulaRequest $request, Partido $partido, string $lado): RedirectResponse
    {
        $this->cierre->firmar($this->cedulas->abierta($partido), $lado, $request->png());

        return redirect()->route('cedulas.cierre', $partido)->with('success', 'Firma guardada.');
    }

    // La imagen de la firma, desde el disco privado; solo para quien puede consultar la cédula
    public function firma(Partido $partido, string $lado): StreamedResponse
    {
        Gate::authorize('ver', [CedulaArbitral::class, $partido]);

        $cedula = $partido->cedula()->firstOrFail();
        $ruta = $this->cierre->rutaDeFirma($cedula, $lado);
        abort_if($ruta === null, 404);

        return Storage::disk('local')->response($ruta, 'firma-'.$lado.'.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function cerrar(CerrarCedulaRequest $request, Partido $partido): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $this->cierre->cerrar($this->cedulas->abierta($partido), $usuario);

        return redirect()->route('cedulas.show', $partido)->with('success', 'Cédula cerrada: ya no se puede modificar.');
    }
}
