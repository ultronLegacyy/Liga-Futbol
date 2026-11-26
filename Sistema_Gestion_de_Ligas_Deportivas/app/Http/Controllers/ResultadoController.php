<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorregirResultadoRequest;
use App\Models\CedulaArbitral;
use App\Models\User;
use App\Services\CorreccionResultadoService;
use App\Services\ValidacionResultadoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// HU-040 (RF-08.4): validar oficialmente los resultados capturados. Solo el Administrador General (CedulaArbitralPolicy)
class ResultadoController extends Controller
{
    public function __construct(private readonly ValidacionResultadoService $validacion)
    {
    }

    // Resultados con la cédula cerrada que esperan validación y los últimos ya oficiales
    public function index(): View
    {
        Gate::authorize('validarResultados', CedulaArbitral::class);

        return view('resultados.index', [
            'pendientes' => $this->validacion->pendientes(),
            'recientes' => $this->validacion->recientes(),
        ]);
    }

    // CA2: el resultado pasa a oficial y dispara el cálculo de la tabla (HU-041)
    public function validar(Request $request, CedulaArbitral $cedula): RedirectResponse
    {
        Gate::authorize('validarResultados', CedulaArbitral::class);

        /** @var User $usuario */
        $usuario = $request->user();

        $this->validacion->validar($cedula, $usuario);

        $partido = $cedula->partido()->with(['local:id,nombre', 'visitante:id,nombre'])->first();

        return redirect()
            ->route('resultados.index')
            ->with('success', 'Resultado validado: '.$partido?->local?->nombre.' '.$cedula->goles_local.' - '.$cedula->goles_visitante.' '.$partido?->visitante?->nombre.'. Ya es oficial.');
    }

    // HU-039: formulario para corregir un resultado ya validado, con sus correcciones anteriores
    public function editarCorreccion(CedulaArbitral $cedula, CorreccionResultadoService $correccion): View
    {
        Gate::authorize('corregirResultado', $cedula);

        $cedula->load(['partido.local:id,nombre', 'partido.visitante:id,nombre', 'partido.jornada.grupo.categoria:id,nombre', 'validadaPor:id,name']);

        return view('resultados.corregir', [
            'cedula' => $cedula,
            'historial' => $correccion->historial($cedula),
        ]);
    }

    // HU-039 CA1 y CA2: guarda la corrección con su motivo y recalcula la tabla del grupo
    public function corregir(CorregirResultadoRequest $request, CedulaArbitral $cedula, CorreccionResultadoService $correccion): RedirectResponse
    {
        $datos = $request->validated();
        $correccion->corregir($cedula, (int) $datos['goles_local'], (int) $datos['goles_visitante'], (string) $datos['motivo']);

        return redirect()
            ->route('resultados.corregir.edit', $cedula->id)
            ->with('success', 'Resultado corregido: '.$datos['goles_local'].' - '.$datos['goles_visitante'].'. La tabla de posiciones ya se recalculó.');
    }
}
