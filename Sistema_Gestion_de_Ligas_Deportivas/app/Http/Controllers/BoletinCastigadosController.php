<?php

namespace App\Http\Controllers;

use App\Http\Requests\DespublicarBoletinRequest;
use App\Http\Requests\PublicarBoletinRequest;
use App\Models\Sancion;
use App\Models\Temporada;
use App\Services\VigenciaSancionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Controlador para la consulta y publicación del Boletín Oficial de Castigados (HU-046 / RF-10.3, RNF-44, PO #45).
 */
class BoletinCastigadosController extends Controller
{
    public function __construct(
        private readonly VigenciaSancionService $vigenciaService
    ) {
    }

    /**
     * Consulta el boletín semanal oficial de castigados acotado al rol del usuario (PO #45).
     */
    public function index(Request $request): View
    {
        Gate::authorize('verBoletin', Sancion::class);

        $temporadaId = $request->integer('temporada_id') > 0 ? $request->integer('temporada_id') : null;
        $temporadas = Temporada::orderByDesc('id')->get();
        $usuario = $request->user();

        $boletin = $this->vigenciaService->obtenerBoletinCastigados($temporadaId, $usuario);
        $puedeAdministrar = $usuario !== null && $usuario->can('disciplina.sancionar');

        $sancionesPendientes = $puedeAdministrar
            ? $this->vigenciaService->obtenerSancionesPendientesBoletin($temporadaId)
            : new Collection();

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
        Gate::authorize('publicar', Sancion::class);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validated();
        $ids = array_map('intval', $validated['sanciones_ids']);
        $motivo = $validated['motivo'];

        $totalPublicadas = $this->vigenciaService->publicarEnBoletin($ids, $usuario, $motivo);

        return redirect()
            ->route('disciplina.boletin.index')
            ->with('success', "Se publicaron {$totalPublicadas} sanción(es) en el boletín oficial semanal.");
    }

    /**
     * Retira una sanción del boletín oficial.
     */
    public function despublicar(DespublicarBoletinRequest $request, Sancion $sancion): RedirectResponse
    {
        Gate::authorize('publicar', $sancion);

        $usuario = $request->user();
        if ($usuario === null) {
            abort(403, 'Usuario no autenticado.');
        }

        $validated = $request->validated();
        $this->vigenciaService->despublicarDeBoletin($sancion, $usuario, $validated['motivo']);

        return redirect()
            ->route('disciplina.boletin.index')
            ->with('success', 'La sanción fue retirada del boletín oficial.');
    }
}
