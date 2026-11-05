<?php

namespace App\Http\Controllers;

use App\Models\Arbitro;
use App\Models\Partido;
use App\Models\Persona;
use App\Services\AsignacionArbitroService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Padrón de árbitros colegiados y su asignación a partidos programados (HU-026, RF-03.2 y RF-06.1)
class ArbitroController extends Controller
{
    public function __construct(
        private readonly AsignacionArbitroService $asignacion
    ) {
    }

    /**
     * Listar el padrón de árbitros.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', Arbitro::class);

        $arbitros = Arbitro::query()
            ->with('persona:id,nombre,apellidos')
            ->get()
            ->sortBy(fn (Arbitro $arbitro): string => $arbitro->persona->apellidos.' '.$arbitro->persona->nombre)
            ->values();

        return view('arbitros.index', compact('arbitros'));
    }

    /**
     * Formulario para registrar en el padrón a una persona con rol de árbitro (CA1).
     */
    public function create(): View
    {
        Gate::authorize('create', Arbitro::class);

        return view('arbitros.create', [
            'arbitro' => new Arbitro(['estado' => 'Activo']),
            'candidatas' => $this->personasCandidatas(),
        ]);
    }

    /**
     * Registrar a la persona en el padrón; reutiliza su identidad (HU-013) en vez de capturarla otra vez.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Arbitro::class);

        $datos = $request->validate([
            'persona_id' => [
                'required',
                'integer',
                Rule::exists('personas', 'id'),
                Rule::unique('arbitros', 'persona_id'),
                function (string $atributo, mixed $valor, Closure $fallar): void {
                    $tieneRol = Persona::query()->whereKey($valor)
                        ->whereHas('roles', fn ($roles) => $roles->where('rol', 'arbitro'))
                        ->exists();

                    if (! $tieneRol) {
                        $fallar('La persona debe tener rol de árbitro en alguna temporada.');
                    }
                },
            ],
        ] + $this->reglasArbitro(), $this->mensajes());

        // Sin tarifa capturada queda el valor por defecto de la columna
        $datos['tarifa_por_partido'] ??= 0;

        DB::transaction(fn () => Arbitro::create($datos));

        return redirect()->route('arbitros.index')
            ->with('success', 'Árbitro registrado en el padrón.');
    }

    /**
     * Formulario para cambiar colegio, tarifa o disponibilidad.
     */
    public function edit(Arbitro $arbitro): View
    {
        Gate::authorize('update', $arbitro);

        $arbitro->load('persona');

        return view('arbitros.edit', compact('arbitro'));
    }

    /**
     * Cambiar colegio, tarifa o disponibilidad del árbitro.
     */
    public function update(Request $request, Arbitro $arbitro): RedirectResponse
    {
        Gate::authorize('update', $arbitro);

        $datos = $request->validate($this->reglasArbitro(), $this->mensajes());
        $datos['tarifa_por_partido'] ??= 0;

        DB::transaction(fn () => $arbitro->update($datos));

        return redirect()->route('arbitros.index')
            ->with('success', 'Árbitro actualizado correctamente.');
    }

    /**
     * Partidos programados con su árbitro asignado o pendiente.
     */
    public function asignaciones(): View
    {
        Gate::authorize('asignar', Arbitro::class);

        $partidos = Partido::query()
            ->with(['local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre', 'arbitro.persona:id,nombre,apellidos'])
            ->whereIn('estado', Partido::ESTADOS_ASIGNABLES)
            ->orderBy('fecha_partido')
            ->orderBy('hora_partido')
            ->paginate(25);

        return view('arbitros.asignaciones', compact('partidos'));
    }

    /**
     * Formulario para asignar árbitro a un partido, con los árbitros disponibles en su temporada.
     */
    public function editarAsignacion(Partido $partido): View
    {
        Gate::authorize('asignar', Arbitro::class);

        $partido->load(['local', 'visitante', 'cancha', 'jornada.grupo.temporada', 'arbitro.persona']);
        $temporada = $partido->jornada->grupo->temporada;

        $disponibles = Arbitro::query()
            ->with('persona:id,nombre,apellidos')
            ->where('estado', 'Activo')
            ->whereHas('persona.roles', fn ($roles) => $roles->where('rol', 'arbitro')->where('temporada_id', $temporada->id))
            ->get();

        return view('arbitros.asignar', compact('partido', 'temporada', 'disponibles'));
    }

    /**
     * Asignar el árbitro al partido (CA2) rechazando choques de horario (CA3).
     */
    public function asignar(Request $request, Partido $partido): RedirectResponse
    {
        Gate::authorize('asignar', Arbitro::class);

        $datos = $request->validate([
            'arbitro_id' => ['required', 'integer', Rule::exists('arbitros', 'id')],
        ], [
            'arbitro_id.required' => 'Selecciona un árbitro.',
            'arbitro_id.exists' => 'El árbitro seleccionado no existe.',
            'arbitro_id.integer' => 'El árbitro seleccionado no es válido.',
        ]);

        $this->asignacion->asignar($partido, Arbitro::findOrFail($datos['arbitro_id']));

        return redirect()->route('arbitros.asignaciones')
            ->with('success', 'Árbitro asignado al partido.');
    }

    /**
     * Personas con rol de árbitro que aún no están en el padrón.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Persona>
     */
    private function personasCandidatas()
    {
        return Persona::query()
            ->whereHas('roles', fn ($roles) => $roles->where('rol', 'arbitro'))
            ->whereDoesntHave('arbitro')
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellidos']);
    }

    /**
     * Datos del padrón; la tarifa respeta la columna decimal(10,2).
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglasArbitro(): array
    {
        return [
            'colegio_pertenencia' => ['nullable', 'string', 'max:255'],
            'tarifa_por_partido' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'estado' => ['required', Rule::in(Arbitro::ESTADOS)],
        ];
    }

    /**
     * Mensajes en español mientras no exista la traducción general de la validación (IU-03).
     *
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'persona_id.required' => 'Selecciona a la persona.',
            'persona_id.exists' => 'La persona seleccionada no existe.',
            'persona_id.unique' => 'La persona ya está en el padrón de árbitros.',
            'colegio_pertenencia.max' => 'El colegio no debe tener más de :max caracteres.',
            'tarifa_por_partido.numeric' => 'La tarifa debe ser un número.',
            'tarifa_por_partido.min' => 'La tarifa no puede ser negativa.',
            'tarifa_por_partido.max' => 'La tarifa es demasiado grande.',
            'tarifa_por_partido.decimal' => 'La tarifa admite hasta dos decimales.',
            'estado.required' => 'La disponibilidad es obligatoria.',
            'estado.in' => 'La disponibilidad debe ser Activo o Inactivo.',
        ];
    }
}
