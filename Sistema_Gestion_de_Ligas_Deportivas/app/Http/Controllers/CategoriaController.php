<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Grupo;
use App\Models\Temporada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Categorías de cada temporada (HU-007, RF-02.1): vistas Blade y, si se pide JSON, la misma consulta en JSON
class CategoriaController extends Controller
{
    /**
     * Listar las categorías de una temporada con sus grupos.
     */
    public function index(Request $request, Temporada $temporada): View|JsonResponse
    {
        Gate::authorize('viewAny', Categoria::class);

        $categorias = $temporada->categorias()
            ->with(['grupos' => fn ($consulta) => $consulta->orderBy('nombre')])
            ->orderBy('rama')
            ->orderBy('nombre')
            ->get();

        if (! $request->expectsJson()) {
            return view('categorias.index', compact('temporada', 'categorias'));
        }

        return response()->json([
            'modulo' => 'Categorías',
            'temporada_id' => $temporada->id,
            'temporada_cerrada' => $temporada->estado === 'cerrada',
            'categorias' => $categorias->map(
                fn (Categoria $categoria): array => $this->datosCategoria($categoria)
            )->values(),
        ]);
    }

    /**
     * Consultar una categoría con sus grupos.
     */
    public function show(Request $request, Categoria $categoria): View|JsonResponse
    {
        Gate::authorize('view', $categoria);

        $categoria->load(['grupos' => fn ($consulta) => $consulta->orderBy('nombre'), 'temporada']);

        if (! $request->expectsJson()) {
            return view('categorias.show', compact('categoria'));
        }

        return response()->json(['modulo' => 'Categoría'] + $this->datosCategoria($categoria));
    }

    /**
     * Formulario para agregar una categoría a la temporada.
     */
    public function create(Temporada $temporada): View
    {
        Gate::authorize('create', [Categoria::class, $temporada]);

        return view('categorias.create', ['temporada' => $temporada, 'categoria' => new Categoria()]);
    }

    /**
     * Agregar una categoría a la temporada.
     */
    public function store(Request $request, Temporada $temporada): RedirectResponse
    {
        Gate::authorize('create', [Categoria::class, $temporada]);

        $datos = $request->validate(
            $this->reglasCategoria($request, $temporada->id),
            $this->mensajesCategoria()
        );

        // El alta y su auditoría se guardan juntas o no se guarda nada (RNF-22)
        DB::transaction(fn () => $temporada->categorias()->create($datos));

        return redirect()->route('temporadas.categorias.index', $temporada)
            ->with('success', 'Categoría registrada correctamente.');
    }

    /**
     * Formulario para cambiar los datos de una categoría.
     */
    public function edit(Categoria $categoria): View
    {
        Gate::authorize('update', $categoria);

        $categoria->load('temporada');

        return view('categorias.edit', compact('categoria'));
    }

    /**
     * Renombrar o cambiar los datos de una categoría.
     */
    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        Gate::authorize('update', $categoria);

        $datos = $request->validate(
            $this->reglasCategoria($request, $categoria->temporada_id, $categoria->id),
            $this->mensajesCategoria()
        );

        DB::transaction(fn () => $categoria->update($datos));

        return redirect()->route('categorias.show', $categoria)
            ->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Datos públicos de una categoría y sus grupos.
     *
     * @return array<string, mixed>
     */
    private function datosCategoria(Categoria $categoria): array
    {
        return [
            'id' => $categoria->id,
            'temporada_id' => $categoria->temporada_id,
            'nombre' => $categoria->nombre,
            'rama' => $categoria->rama,
            'anio_nacimiento_desde' => $categoria->anio_nacimiento_desde,
            'anio_nacimiento_hasta' => $categoria->anio_nacimiento_hasta,
            'edad_min' => $categoria->edad_min,
            'grupos' => $categoria->grupos->map(fn (Grupo $grupo): array => [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
            ])->values(),
        ];
    }

    /**
     * Reglas del alta y la edición. El nombre se repite solo en otra rama o en otra temporada.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglasCategoria(Request $request, ?int $temporadaId, ?int $ignorar = null): array
    {
        $rama = $request->input('rama');
        $anioActual = now()->year;

        $reglas = [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categorias', 'nombre')
                    ->where('temporada_id', $temporadaId)
                    ->where('rama', is_string($rama) ? $rama : null)
                    ->ignore($ignorar),
            ],
            'rama' => ['required', 'string', Rule::in(Categoria::RAMAS)],
            'anio_nacimiento_desde' => ['nullable', 'integer', 'min:1900', 'max:'.$anioActual],
            'anio_nacimiento_hasta' => ['nullable', 'integer', 'min:1900', 'max:'.$anioActual],
            'edad_min' => ['nullable', 'integer', 'min:0', 'max:99'],
        ];

        // El rango solo se compara cuando se capturan los dos años
        if ($request->filled('anio_nacimiento_desde')) {
            $reglas['anio_nacimiento_hasta'][] = 'gte:anio_nacimiento_desde';
        }

        return $reglas;
    }

    /**
     * Mensajes en español mientras no exista la traducción general de la validación (IU-03).
     *
     * @return array<string, string>
     */
    private function mensajesCategoria(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.string' => 'El nombre de la categoría debe ser texto.',
            'nombre.max' => 'El nombre de la categoría no debe tener más de :max caracteres.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre en la misma rama de esta temporada.',
            'rama.required' => 'La rama es obligatoria.',
            'rama.string' => 'La rama debe ser Libre, Veteranos, Femenil o Infantil.',
            'rama.in' => 'La rama debe ser Libre, Veteranos, Femenil o Infantil.',
            'anio_nacimiento_desde.integer' => 'El año de nacimiento inicial debe ser un año válido.',
            'anio_nacimiento_desde.min' => 'El año de nacimiento inicial no puede ser anterior a :min.',
            'anio_nacimiento_desde.max' => 'El año de nacimiento inicial no puede ser posterior a :max.',
            'anio_nacimiento_hasta.integer' => 'El año de nacimiento final debe ser un año válido.',
            'anio_nacimiento_hasta.min' => 'El año de nacimiento final no puede ser anterior a :min.',
            'anio_nacimiento_hasta.max' => 'El año de nacimiento final no puede ser posterior a :max.',
            'anio_nacimiento_hasta.gte' => 'El año de nacimiento final no puede ser anterior al inicial.',
            'edad_min.integer' => 'La edad mínima debe ser un número entero.',
            'edad_min.min' => 'La edad mínima no puede ser negativa.',
            'edad_min.max' => 'La edad mínima no puede ser mayor que :max años.',
        ];
    }
}
