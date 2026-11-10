<?php

namespace App\Http\Controllers;

use App\Models\AcuerdoAsamblea;
use App\Models\Asamblea;
use App\Models\Temporada;
use App\Models\User;
use App\Services\AsambleaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Asambleas de delegados: convocatoria y lista de asistencia (HU-053, RF-14.1)
class AsambleaController extends Controller
{
    public function __construct(private readonly AsambleaService $asambleas)
    {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Asamblea::class);

        $asambleas = Asamblea::query()
            ->with('temporada')
            ->withCount(['asistencias as asistentes_count' => fn ($consulta) => $consulta->where('asistio', true)])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20);

        return view('asambleas.index', compact('asambleas'));
    }

    public function create(): View
    {
        Gate::authorize('create', Asamblea::class);

        return view('asambleas.create', [
            'temporadas' => Temporada::query()->orderByDesc('fecha_inicio')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Asamblea::class);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        /** @var array{tipo: string, fecha: string, hora: string, lugar: string, orden_del_dia: string, temporada_id?: int|string|null} $datos */
        $datos = $request->validate([
            'tipo' => ['required', 'string', Rule::in(Asamblea::TIPOS)],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],
            'lugar' => ['required', 'string', 'min:3', 'max:200'],
            'orden_del_dia' => ['required', 'string', 'min:5', 'max:5000'],
            'temporada_id' => ['nullable', 'integer', Rule::exists('temporadas', 'id')],
        ], [
            'tipo.required' => 'Selecciona el tipo de asamblea.',
            'tipo.in' => 'El tipo de asamblea debe ser Ordinaria o Extraordinaria.',
            'fecha.required' => 'La fecha de la asamblea es obligatoria.',
            'fecha.after_or_equal' => 'No se puede convocar una asamblea en una fecha pasada.',
            'hora.required' => 'La hora de la asamblea es obligatoria.',
            'hora.date_format' => 'La hora debe tener el formato HH:MM.',
            'lugar.required' => 'El lugar de la asamblea es obligatorio.',
            'orden_del_dia.required' => 'El orden del día es obligatorio.',
            'orden_del_dia.min' => 'Describe el orden del día de la asamblea.',
            'temporada_id.exists' => 'La temporada seleccionada no existe.',
        ]);

        $asamblea = $this->asambleas->convocar($datos, $usuario);

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'La asamblea fue convocada. Registra la asistencia de los delegados el día de la reunión.');
    }

    public function show(Asamblea $asamblea): View
    {
        Gate::authorize('view', $asamblea);
        $asamblea->load([
            'temporada', 'convocadaPor', 'asistencias.club', 'asistencias.delegado',
            'acuerdosRegistrados.temporada', 'acuerdosRegistrados.autorizadoPor', 'comunicados.registradoPor',
        ]);

        return view('asambleas.show', [
            'asamblea' => $asamblea,
            'temporadas' => Temporada::query()->orderByDesc('fecha_inicio')->get(),
        ]);
    }

    public function editarAsistencia(Asamblea $asamblea): View
    {
        Gate::authorize('registrarAsistencia', $asamblea);
        $clubes = $this->asambleas->clubesConvocados($asamblea);

        return view('asambleas.asistencia', [
            'asamblea' => $asamblea,
            'clubes' => $clubes,
            'delegados' => $this->asambleas->delegadosPorClub($clubes),
            'asistencias' => $asamblea->asistencias()->get()->keyBy('club_id'),
        ]);
    }

    public function guardarAsistencia(Request $request, Asamblea $asamblea): RedirectResponse
    {
        Gate::authorize('registrarAsistencia', $asamblea);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        $datos = $request->validate([
            'asistencias' => ['required', 'array'],
            'asistencias.*' => ['array'],
            'asistencias.*.asistio' => ['nullable', 'boolean'],
            'asistencias.*.delegado_usuario_id' => ['nullable', 'integer'],
            'asistencias.*.nombre_asistente' => ['nullable', 'string', 'max:200'],
            'asistencias.*.observaciones' => ['nullable', 'string', 'max:255'],
        ], [
            'asistencias.required' => 'La lista de asistencia está vacía.',
            'asistencias.*.nombre_asistente.max' => 'El nombre del asistente no debe superar los 200 caracteres.',
            'asistencias.*.observaciones.max' => 'Las observaciones no deben superar los 255 caracteres.',
        ]);

        /** @var array<int|string, array{asistio?: mixed, delegado_usuario_id?: int|string|null, nombre_asistente?: string|null, observaciones?: string|null}> $lista */
        $lista = $datos['asistencias'];
        $this->asambleas->registrarAsistencia($asamblea, $lista, $usuario);

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'La lista de asistencia quedó guardada junto con la convocatoria.');
    }

    // Acuerdos de la asamblea e indicación de si modifican el reglamento (HU-054, RF-14.2)
    public function guardarAcuerdo(Request $request, Asamblea $asamblea): RedirectResponse
    {
        Gate::authorize('registrarAcuerdos', $asamblea);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        /** @var array{descripcion: string, modifica_reglamento?: mixed, temporada_id?: int|string|null} $datos */
        $datos = $request->validate([
            'descripcion' => ['required', 'string', 'min:5', 'max:5000'],
            'modifica_reglamento' => ['required', 'boolean'],
            'temporada_id' => ['nullable', 'integer', Rule::exists('temporadas', 'id')],
        ], [
            'descripcion.required' => 'Describe el acuerdo.',
            'descripcion.min' => 'Describe el acuerdo.',
            'modifica_reglamento.required' => 'Indica si el acuerdo modifica el reglamento de la temporada.',
            'temporada_id.exists' => 'La temporada seleccionada no existe.',
        ]);

        $acuerdo = $this->asambleas->registrarAcuerdo($asamblea, $datos, $usuario);

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'Acuerdo '.$acuerdo->numero.' registrado.');
    }

    // Convertir un acuerdo en regla permanente solo con autorización (HU-054, RF-14.3)
    public function autorizarAcuerdo(Request $request, Asamblea $asamblea, AcuerdoAsamblea $acuerdo): RedirectResponse
    {
        abort_unless((int) $acuerdo->asamblea_id === (int) $asamblea->getKey(), 404);
        Gate::authorize('autorizarReglaPermanente', $asamblea);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'motivo.required' => 'Explica por qué el acuerdo se vuelve regla permanente.',
            'motivo.min' => 'Explica por qué el acuerdo se vuelve regla permanente.',
        ]);

        $this->asambleas->convertirEnReglaPermanente($acuerdo, $datos['motivo'], $usuario);

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'El acuerdo '.$acuerdo->numero.' quedó autorizado como regla permanente.');
    }

    // Comunicados de la asamblea (HU-054, RF-14.2)
    public function guardarComunicado(Request $request, Asamblea $asamblea): RedirectResponse
    {
        Gate::authorize('registrarAcuerdos', $asamblea);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        /** @var array{titulo: string, contenido: string} $datos */
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'min:3', 'max:200'],
            'contenido' => ['required', 'string', 'min:5', 'max:10000'],
        ], [
            'titulo.required' => 'El título del comunicado es obligatorio.',
            'contenido.required' => 'El contenido del comunicado es obligatorio.',
        ]);

        $this->asambleas->registrarComunicado($asamblea, $datos, $usuario);

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'Comunicado registrado.');
    }
}
