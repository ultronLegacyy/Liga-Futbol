<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicarBoletinRequest;
use App\Models\Sancion;
use App\Models\Temporada;
use App\Services\VigenciaSancionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Controlador para la consulta y publicación del Boletín Oficial de Castigados (HU-046 / RF-10.3, RNF-44).
 */
class BoletinCastigadosController extends Controller
{
    public function __construct(
        private readonly VigenciaSancionService $vigenciaService
    ) {
    }

    /**
     * Consulta el boletín semanal oficial de castigados (HU-046 Criterios 2 y 3, RNF-44).
     */
    public function index(Request $request): View
    {
        $temporadaId = $request->integer('temporada_id') > 0 ? $request->integer('temporada_id') : null;
        $temporadas = Temporada::orderByDesc('id')->get();

        $boletin = $this->vigenciaService->obtenerBoletinCastigados($temporadaId);

        $usuario = $request->user();
        $puedeAdministrar = $usuario !== null && $usuario->can('disciplina.sancionar');

        $sancionesPendientes = new \Illuminate\Database\Eloquent\Collection();
        if ($puedeAdministrar) {
            $sancionesPendientes = Sancion::query()
                ->with(['expediente', 'persona', 'equipo', 'articulo'])
                ->where('estado', Sancion::ESTADO_VIGENTE)
                ->where('publicada_en_boletin', false)
                ->orderByDesc('id')
                ->get();
        }

        return view('disciplina.boletin.index', [
            'boletin' => $boletin,
            'temporadas' => $temporadas,
            'temporadaSeleccionada' => $temporadaId,
            'puedeAdministrar' => $puedeAdministrar,
            'sancionesPendientes' => $sancionesPendientes,
        ]);
    }

    /**
     * Publica sanciones validadas en el boletín semanal (RNF-44).
     */
    public function publicar(PublicarBoletinRequest $request): RedirectResponse
    {
        Gate::authorize('create', Sancion::class);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validated();
        $ids = array_map('intval', $validated['sanciones_ids']);
        $motivo = $validated['motivo'];
        $cargo = $validated['autorizado_por_cargo'] ?? null;

        $totalPublicadas = $this->vigenciaService->publicarEnBoletin($ids, $usuario, $motivo, $cargo);

        return redirect()
            ->route('disciplina.boletin.index')
            ->with('success', "Se publicaron {$totalPublicadas} sanción(es) en el boletín oficial semanal.");
    }

    /**
     * Retira una sanción del boletín oficial.
     */
    public function despublicar(Request $request, Sancion $sancion): RedirectResponse
    {
        Gate::authorize('create', Sancion::class);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->vigenciaService->despublicarDeBoletin($sancion, $usuario, $validated['motivo']);

        return redirect()
            ->route('disciplina.boletin.index')
            ->with('success', 'La sanción fue retirada del boletín oficial.');
    }
}
