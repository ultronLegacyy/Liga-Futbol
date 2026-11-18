<?php

namespace App\Http\Controllers;

use App\Models\CatalogoArticulo;
use App\Models\ExpedienteDisciplinario;
use App\Services\ExpedienteDisciplinarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Controlador para la visualización y gestión de expedientes disciplinarios (HU-044 / RF-10.1).
 * Tribunal de Penas / Comisión Disciplinaria.
 */
class ExpedienteDisciplinarioController extends Controller
{
    public function __construct(
        private readonly ExpedienteDisciplinarioService $expedientesService,
    ) {
    }

    /**
     * Listado general de expedientes disciplinarios con filtros de búsqueda y estado.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ExpedienteDisciplinario::class);

        $query = ExpedienteDisciplinario::query()
            ->with([
                'partido.local:id,nombre',
                'partido.visitante:id,nombre',
                'partido.jornada:id,numero_jornada,grupo_id',
                'articulo:id,codigo,descripcion,castigo_base_partidos',
                'articulos:id,codigo,descripcion,castigo_base_partidos',
            ])
            ->withCount(['involucrados', 'evidencias']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        if ($request->filled('buscar')) {
            $busqueda = '%'.$request->query('buscar').'%';
            $query->where(function ($sub) use ($busqueda) {
                $sub->where('folio', 'like', $busqueda)
                    ->orWhere('titulo', 'like', $busqueda)
                    ->orWhere('descripcion_hechos', 'like', $busqueda)
                    ->orWhereHas('partido.local', fn ($q) => $q->where('nombre', 'like', $busqueda))
                    ->orWhereHas('partido.visitante', fn ($q) => $q->where('nombre', 'like', $busqueda));
            });
        }

        $expedientes = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('disciplina.expedientes.index', [
            'expedientes' => $expedientes,
            'estadoFiltro' => $request->query('estado', ''),
            'busqueda' => $request->query('buscar', ''),
        ]);
    }

    /**
     * Consulta exhaustiva del expediente disciplinario con personas, equipos, evidencias
     * y artículos del catálogo presuntamente infringidos (HU-044 Criterio 2).
     */
    public function show(ExpedienteDisciplinario $expediente): View
    {
        Gate::authorize('view', $expediente);

        $expedienteCompleto = $this->expedientesService->obtenerExpediente($expediente);
        $todosLosArticulos = CatalogoArticulo::orderBy('codigo')->get();

        return view('disciplina.expedientes.show', [
            'expediente' => $expedienteCompleto,
            'catalogoArticulos' => $todosLosArticulos,
        ]);
    }

    /**
     * Imputa un artículo reglamentario presunto adicional al expediente (Tribunal de Penas).
     */
    public function asociarArticulo(Request $request, ExpedienteDisciplinario $expediente): RedirectResponse
    {
        Gate::authorize('gestionar', $expediente);

        $validated = $request->validate([
            'articulo_id' => ['required', 'integer', 'exists:catalogo_articulos,id'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        $this->expedientesService->asociarArticuloPresunto(
            $expediente,
            (int) $validated['articulo_id'],
            $validated['observaciones'] ?? null
        );

        return redirect()->route('disciplina.expedientes.show', $expediente)
            ->with('success', 'Artículo reglamentario presuntamente infringido asociado exitosamente.');
    }

    /**
     * Remueve un artículo reglamentario presunto del expediente.
     */
    public function desasociarArticulo(Request $request, ExpedienteDisciplinario $expediente, CatalogoArticulo $articulo): RedirectResponse
    {
        Gate::authorize('gestionar', $expediente);

        $this->expedientesService->desasociarArticuloPresunto($expediente, $articulo->id);

        return redirect()->route('disciplina.expedientes.show', $expediente)
            ->with('success', 'Artículo reglamentario presunto desestimado del expediente.');
    }
}
