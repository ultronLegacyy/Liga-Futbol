<?php

namespace App\Http\Controllers;

use App\Models\AcuerdoAsamblea;
use App\Models\Asamblea;
use App\Models\Temporada;
use App\Models\User;
use App\Http\Requests\AutorizarAcuerdoAsambleaRequest;
use App\Http\Requests\GuardarAsistenciaAsambleaRequest;
use App\Http\Requests\StoreAcuerdoAsambleaRequest;
use App\Http\Requests\StoreAsambleaRequest;
use App\Http\Requests\StoreComunicadoAsambleaRequest;
use App\Services\AsambleaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

    public function store(StoreAsambleaRequest $request): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        /** @var array{tipo: string, fecha: string, hora: string, lugar: string, orden_del_dia: string, temporada_id?: int|string|null} $datos */
        $datos = $request->validated();
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

    public function guardarAsistencia(GuardarAsistenciaAsambleaRequest $request, Asamblea $asamblea): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        /** @var array<int|string, array{asistio?: mixed, delegado_usuario_id?: int|string|null, nombre_asistente?: string|null, observaciones?: string|null}> $lista */
        $lista = $request->validated('asistencias');
        $this->asambleas->registrarAsistencia($asamblea, $lista, $usuario);

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'La lista de asistencia quedó guardada junto con la convocatoria.');
    }

    // Acuerdos de la asamblea e indicación de si modifican el reglamento (HU-054, RF-14.2)
    public function guardarAcuerdo(StoreAcuerdoAsambleaRequest $request, Asamblea $asamblea): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        /** @var array{descripcion: string, modifica_reglamento?: mixed, temporada_id?: int|string|null} $datos */
        $datos = $request->validated();
        $acuerdo = $this->asambleas->registrarAcuerdo($asamblea, $datos, $usuario);

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'Acuerdo '.$acuerdo->numero.' registrado.');
    }

    // Convertir un acuerdo en regla permanente solo con autorización (HU-054, RF-14.3)
    public function autorizarAcuerdo(AutorizarAcuerdoAsambleaRequest $request, Asamblea $asamblea, AcuerdoAsamblea $acuerdo): RedirectResponse
    {
        abort_unless((int) $acuerdo->asamblea_id === (int) $asamblea->getKey(), 404);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        try {
            $this->asambleas->convertirEnReglaPermanente($acuerdo, (string) $request->validated('motivo'), $usuario);
        } catch (ValidationException $error) {
            // El error del servicio también va a la bolsa del acuerdo, debajo de su botón.
            throw $error->errorBag(AutorizarAcuerdoAsambleaRequest::bolsaDeErrores($acuerdo));
        }

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'El acuerdo '.$acuerdo->numero.' quedó autorizado como regla permanente.');
    }

    // Comunicados de la asamblea (HU-054, RF-14.2)
    public function guardarComunicado(StoreComunicadoAsambleaRequest $request, Asamblea $asamblea): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        /** @var array{titulo: string, contenido: string} $datos */
        $datos = $request->validated();
        $this->asambleas->registrarComunicado($asamblea, $datos, $usuario);

        return redirect()->route('asambleas.show', $asamblea)
            ->with('success', 'Comunicado registrado.');
    }
}
