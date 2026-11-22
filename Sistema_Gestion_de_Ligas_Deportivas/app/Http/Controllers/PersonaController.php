<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\PersonaRol;
use App\Models\Temporada;
use App\Services\DuplicadosService;
use App\Services\IdentidadPersonaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Padrón de personas con sus roles por temporada (HU-013, RF-03.2): una persona, varios roles, sin duplicar su identidad
class PersonaController extends Controller
{
    public function __construct(
        private readonly IdentidadPersonaService $identidad,
        private readonly DuplicadosService $duplicados
    ) {
    }

    /**
     * Listar las personas con sus roles.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', Persona::class);

        $personas = Persona::query()
            ->with('roles.temporada:id,nombre')
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->paginate(25);

        return view('personas.index', compact('personas'));
    }

    /**
     * Formulario para registrar a una persona con un rol; si su CURP ya existe se reutiliza su identidad.
     */
    public function create(): View
    {
        Gate::authorize('create', Persona::class);

        return view('personas.create', [
            'temporadas' => $this->temporadasAbiertas(),
            'roles' => PersonaRol::ROLES,
        ]);
    }

    /**
     * Registrar a una persona con un rol en una temporada (CA1). Si se parece a otra ya registrada,
     * primero se alerta y solo se guarda cuando el usuario confirma esos mismos datos (HU-014, CA1).
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Persona::class);

        $datos = $request->validate($this->reglasPersona() + $this->reglasRol(), $this->mensajes());

        $identidad = [
            'nombre' => $datos['nombre'],
            'apellidos' => $datos['apellidos'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
            'curp' => $datos['curp'],
        ];
        $similares = $this->duplicados->personasSimilares($identidad);
        $huella = $this->duplicados->huella($identidad);

        if ($similares !== [] && $request->input('confirmar_duplicado') !== $huella) {
            return redirect()->route('personas.create')
                ->withInput()
                ->with('posibles_duplicados', array_map(fn (array $similar): array => [
                    'id' => $similar['persona']->id,
                    'nombre' => $similar['persona']->apellidos.', '.$similar['persona']->nombre,
                    'fecha_nacimiento' => $similar['persona']->fecha_nacimiento->format('d/m/Y'),
                    'motivo' => $similar['motivo'],
                ], $similares))
                ->with('huella_duplicado', $huella);
        }

        $rol = $this->identidad->asignarRol(
            $datos,
            $datos['rol'],
            Temporada::findOrFail($datos['temporada_id'])
        );

        // Con la misma CURP y otros datos se reutiliza la identidad tal como estaba registrada (HU-013)
        $mismaCurpConOtrosDatos = collect($similares)->contains(fn (array $similar): bool => $similar['persona']->id === $rol->persona_id);

        $mensaje = match (true) {
            $rol->persona->wasRecentlyCreated => 'Persona registrada con su rol.',
            $mismaCurpConOtrosDatos => 'La persona ya estaba registrada: se reutilizó su identidad sin cambiar sus datos y se le asignó el rol.',
            default => 'La persona ya estaba registrada: se reutilizó su identidad y se le asignó el rol.',
        };

        return redirect()->route('personas.show', $rol->persona_id)->with('success', $mensaje);
    }

    /**
     * Consultar una persona con sus roles por temporada (CA2).
     */
    public function show(Persona $persona): View
    {
        Gate::authorize('view', $persona);

        return view('personas.show', [
            'persona' => $persona,
            'rolesPorTemporada' => $persona->rolesPorTemporada(),
            'equiposPorTemporada' => $persona->equiposPorTemporada(),
            'temporadas' => $this->temporadasAbiertas(),
            'roles' => PersonaRol::ROLES,
        ]);
    }

    /**
     * Asignar un rol más a una persona registrada, sin volver a capturar sus datos (CA1).
     */
    public function asignarRol(Request $request, Persona $persona): RedirectResponse
    {
        Gate::authorize('create', Persona::class);

        $datos = $request->validate($this->reglasRol(), $this->mensajes());

        $this->identidad->asignarRol(
            [
                'nombre' => $persona->nombre,
                'apellidos' => $persona->apellidos,
                'fecha_nacimiento' => $persona->fecha_nacimiento->format('Y-m-d'),
                'curp' => $persona->curp,
            ],
            $datos['rol'],
            Temporada::findOrFail($datos['temporada_id'])
        );

        return redirect()->route('personas.show', $persona)
            ->with('success', 'Rol asignado correctamente.');
    }

    /**
     * Temporadas que admiten roles nuevos: una cerrada solo se consulta (RF-01.4).
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

    /**
     * Datos de identidad; la CURP mide 18 caracteres, como la columna.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglasPersona(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ['required', 'date_format:Y-m-d', 'before:today'],
            'curp' => ['required', 'string', 'alpha_num:ascii', 'size:18'],
        ];
    }

    /**
     * Rol y temporada que se asignan.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglasRol(): array
    {
        return [
            'rol' => ['required', Rule::in(PersonaRol::ROLES)],
            'temporada_id' => ['required', 'integer', Rule::exists('temporadas', 'id')],
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
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no debe tener más de :max caracteres.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.max' => 'Los apellidos no deben tener más de :max caracteres.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.date_format' => 'La fecha de nacimiento no es válida.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'curp.required' => 'La CURP es obligatoria.',
            'curp.alpha_num' => 'La CURP solo puede tener letras y números.',
            'curp.size' => 'La CURP debe tener :size caracteres.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol debe ser jugador, delegado, árbitro o directivo.',
            'temporada_id.required' => 'La temporada es obligatoria.',
            'temporada_id.exists' => 'La temporada seleccionada no existe.',
            'temporada_id.integer' => 'La temporada seleccionada no es válida.',
        ];
    }
}
