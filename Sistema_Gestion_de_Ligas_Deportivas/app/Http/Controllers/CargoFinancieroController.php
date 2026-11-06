<?php

namespace App\Http\Controllers;

use App\Models\CargoFinanciero;
use App\Models\Club;
use App\Models\Equipo;
use App\Models\Persona;
use App\Models\Temporada;
use App\Services\RegistroCargoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Cuotas de inscripción y cargos de arbitraje y cancha por temporada (HU-050, RF-13.1); los pagos son de HU-051
class CargoFinancieroController extends Controller
{
    // Casos de registro: qué concepto generan y si piden equipo o persona ('no', 'opcional' u 'obligatorio')
    public const TIPOS = [
        'inscripcion_club' => ['concepto' => 'Inscripcion', 'etiqueta' => 'Inscripción de un club', 'equipo' => 'no', 'persona' => 'no'],
        'inscripcion_equipo' => ['concepto' => 'Inscripcion', 'etiqueta' => 'Inscripción de un equipo', 'equipo' => 'obligatorio', 'persona' => 'no'],
        'inscripcion_jugador' => ['concepto' => 'Inscripcion', 'etiqueta' => 'Inscripción de un jugador', 'equipo' => 'opcional', 'persona' => 'obligatorio'],
        'arbitraje' => ['concepto' => 'Arbitraje', 'etiqueta' => 'Pago de arbitraje', 'equipo' => 'opcional', 'persona' => 'no'],
        'cancha' => ['concepto' => 'Cancha', 'etiqueta' => 'Pago de cancha', 'equipo' => 'opcional', 'persona' => 'no'],
    ];

    public function __construct(private readonly RegistroCargoService $registro)
    {
    }

    /**
     * Listar los cargos, filtrados por temporada, club y concepto.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CargoFinanciero::class);

        $filtros = [
            'temporada_id' => $request->integer('temporada_id') ?: null,
            'club_id' => $request->integer('club_id') ?: null,
            'concepto' => array_key_exists((string) $request->query('concepto'), CargoFinanciero::CONCEPTOS) ? (string) $request->query('concepto') : null,
        ];

        $cargos = CargoFinanciero::query()
            // Sin la CURP: de la persona solo se muestra el nombre
            ->with(['club:id,nombre', 'equipo:id,nombre', 'persona:id,nombre,apellidos', 'temporada:id,nombre'])
            ->when($filtros['temporada_id'], fn ($consulta, $id) => $consulta->where('temporada_id', $id))
            ->when($filtros['club_id'], fn ($consulta, $id) => $consulta->where('club_id', $id))
            ->when($filtros['concepto'], fn ($consulta, $concepto) => $consulta->where('concepto', $concepto))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('cargos.index', [
            'cargos' => $cargos,
            'filtros' => $filtros,
            'temporadas' => Temporada::query()->orderByDesc('fecha_inicio')->get(['id', 'nombre']),
            'clubes' => Club::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /**
     * Paso 1: elegir el tipo de cargo y la temporada. Paso 2: capturar solo los campos que ese caso necesita.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', CargoFinanciero::class);

        $temporadas = $this->temporadasAbiertas();
        $tipo = (string) $request->query('tipo');
        // Una temporada cerrada solo se consulta (RF-01.4): no aparece y no permite pasar al paso 2
        $temporada = $temporadas->firstWhere('id', $request->integer('temporada_id'));

        if (! array_key_exists($tipo, self::TIPOS) || ! $temporada) {
            return view('cargos.create', ['temporadas' => $temporadas, 'tipo' => null, 'temporada' => null]);
        }

        return view('cargos.create', [
            'temporadas' => $temporadas,
            'tipo' => $tipo,
            'temporada' => $temporada,
            'clubes' => Club::query()->orderBy('nombre')->get(['id', 'nombre']),
            'equipos' => Equipo::query()
                ->with('club:id,nombre')
                ->whereHas('grupo', fn ($consulta) => $consulta->where('temporada_id', $temporada->id))
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'club_id']),
            'jugadores' => Persona::query()
                ->whereHas('roles', fn ($consulta) => $consulta->where('rol', 'jugador')->where('temporada_id', $temporada->id))
                ->orderBy('apellidos')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'apellidos']),
        ]);
    }

    /**
     * Registrar el cargo con RegistroCargoService, que lo liga a su temporada (criterio 1) y a su club o equipo (criterio 2).
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CargoFinanciero::class);

        $tipo = (string) $request->input('tipo');
        $caso = self::TIPOS[$tipo] ?? null;

        $request->validate([
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'equipo_id' => [($caso['equipo'] ?? null) === 'obligatorio' ? 'required' : 'nullable'],
            'persona_id' => [($caso['persona'] ?? null) === 'obligatorio' ? 'required' : 'nullable'],
        ], [
            'tipo.required' => 'El tipo de cargo es obligatorio.',
            'tipo.in' => 'El tipo de cargo seleccionado no es válido.',
            'equipo_id.required' => 'El equipo es obligatorio para la inscripción de un equipo.',
            'persona_id.required' => 'El jugador es obligatorio para la inscripción de un jugador.',
        ]);

        // Los campos que el caso no usa se descartan aunque lleguen
        $cargo = $this->registro->registrar([
            'concepto' => $caso['concepto'] ?? null,
            'temporada_id' => $request->input('temporada_id'),
            'club_id' => $request->input('club_id'),
            'equipo_id' => ($caso['equipo'] ?? 'no') === 'no' ? null : $request->input('equipo_id'),
            'persona_id' => ($caso['persona'] ?? 'no') === 'no' ? null : $request->input('persona_id'),
            'monto' => $request->input('monto'),
            'vencimiento' => $request->input('vencimiento'),
        ]);

        return redirect()->route('cargos.show', $cargo)->with('success', 'Cargo registrado como pendiente.');
    }

    /**
     * Consultar el detalle de un cargo.
     */
    public function show(CargoFinanciero $cargo): View
    {
        Gate::authorize('view', $cargo);

        $cargo->load(['club:id,nombre', 'equipo:id,nombre', 'persona:id,nombre,apellidos', 'temporada:id,nombre,estado']);

        return view('cargos.show', compact('cargo'));
    }

    /**
     * Temporadas que admiten cargos nuevos: una cerrada solo se consulta (RF-01.4).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Temporada>
     */
    private function temporadasAbiertas()
    {
        return Temporada::query()
            ->where('estado', '!=', 'cerrada')
            ->orderByDesc('fecha_inicio')
            ->get(['id', 'nombre']);
    }
}
