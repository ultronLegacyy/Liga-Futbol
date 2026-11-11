<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Http\Requests\BajaJugadorRequest;
use App\Http\Requests\CambiarEstadoJugadorRequest;
use App\Http\Requests\RenovarJugadorRequest;
use App\Http\Requests\TransferirJugadorRequest;
use App\Http\Requests\VincularJugadorRequest;
use App\Models\DocumentoPersona;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EstadoJugadorService;
use App\Services\RegistroJugadorService;
use App\Services\GestionJugadorService;
use App\Services\HistorialCambiosJugadorService;
use App\Services\VinculacionJugadorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JugadorController extends Controller
{
    /**
     * Listado de jugadores para llegar a la ficha (HU-017 y HU-018): el Delegado y el Presidente
     * del Club ven solo los de su club; el Secretario y el Administrador General ven todos.
     * Búsqueda por nombre o CURP.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Jugador::class);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $busqueda = trim((string) $request->query('q', ''));
        $soloSuClub = ! $usuario->hasAnyRole(['Administrador General', 'Secretario']);

        $jugadores = Jugador::query()
            ->with(['persona', 'equipo.club', 'equipo.temporada', 'equipo.grupo.categoria'])
            ->when($soloSuClub, fn (Builder $query): Builder => $query->whereHas('equipo', fn (Builder $equipo): Builder => $equipo->where('club_id', $usuario->club_id)))
            ->when($busqueda !== '', fn (Builder $query): Builder => $query->whereHas('persona', function (Builder $persona) use ($busqueda): void {
                $persona->where('nombre', 'like', '%'.$busqueda.'%')
                    ->orWhere('apellidos', 'like', '%'.$busqueda.'%')
                    ->orWhere('curp', 'like', '%'.strtoupper($busqueda).'%');
            }))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('jugadores.index', compact('jugadores', 'busqueda'));
    }

    public function create(Request $request): View
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless($usuario->can('jugadores.crear'), 403);

        if ($usuario->hasRole('Delegado del Club')) {
            abort_unless($usuario->club_id !== null, 403);
        }

        $equipos = Equipo::query()
            ->with(['temporada', 'grupo.categoria'])
            ->whereHas('temporada', fn (Builder $query): Builder => $query->where('estado', 'activa'))
            ->when(
                $usuario->hasRole('Delegado del Club'),
                fn (Builder $query): Builder => $query->where('club_id', $usuario->club_id),
            )
            ->orderBy('nombre')
            ->get();

        $temporadas = $equipos->pluck('temporada')->unique('id')->values();
        $categorias = $equipos->pluck('grupo.categoria')->unique('id')->values();

        return view('jugadores.create', compact('equipos', 'temporadas', 'categorias'));
    }

    public function darDeBaja(BajaJugadorRequest $request, Jugador $jugador, GestionJugadorService $gestion): JsonResponse|RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $datos = $request->validated();
        $jugador = $gestion->darDeBaja($jugador, $datos['motivo'], $datos['fecha_baja'], $usuario);

        return $request->expectsJson()
            ? response()->json(['mensaje' => 'El jugador fue dado de baja.', 'jugador' => ['id' => $jugador->getKey(), 'estado' => $jugador->estado_elegibilidad, 'motivo_baja' => $jugador->motivo_baja, 'fecha_baja' => $jugador->fecha_baja?->toDateString()]])
            : redirect()->route('jugadores.gestion', $jugador)->with('success', 'El jugador fue dado de baja.');
    }

    public function renovar(RenovarJugadorRequest $request, Jugador $jugador, GestionJugadorService $gestion): JsonResponse|RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $datos = $request->validated();
        $renovacion = $gestion->renovar($jugador, Equipo::query()->findOrFail($datos['equipo_id']), $usuario, $datos['dorsal'] ?? null);

        return $request->expectsJson()
            ? response()->json(['mensaje' => 'La inscripción fue renovada.', 'inscripcion' => ['id' => $renovacion->getKey(), 'persona_id' => $renovacion->persona_id, 'equipo_id' => $renovacion->equipo_id, 'temporada_id' => Equipo::query()->findOrFail($renovacion->equipo_id)->temporada_id, 'dorsal' => $renovacion->dorsal]], 201)
            : redirect()->route('jugadores.gestion', $renovacion)->with('success', 'La inscripción fue renovada.');
    }

    public function transferir(TransferirJugadorRequest $request, Jugador $jugador, GestionJugadorService $gestion): JsonResponse|RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $datos = $request->validated();
        $transferencia = $gestion->transferir($jugador, (int) $datos['equipo_id'], $usuario, $datos['dorsal'] ?? null);
        $transferencia->load('inscripcionDestino.equipo', 'clubDestino');
        $mensaje = 'La transferencia a '.$transferencia->clubDestino->nombre.' fue realizada.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje, 'transferencia' => ['id' => $transferencia->getKey(), 'persona_id' => $transferencia->persona_id, 'jugador_destino_id' => $transferencia->jugador_destino_id, 'club_origen_id' => $transferencia->club_origen_id, 'club_destino_id' => $transferencia->club_destino_id, 'equipo_origen_id' => $transferencia->equipo_origen_id, 'equipo_destino_id' => $transferencia->equipo_destino_id, 'temporada_id' => $transferencia->temporada_id]], 201);
        }

        // El Delegado de origen ya no puede ver la ficha del club destino: vuelve al listado de su club.
        $destino = $transferencia->inscripcionDestino;

        return $destino !== null && Gate::forUser($usuario)->allows('view', $destino)
            ? redirect()->route('jugadores.gestion', $destino)->with('success', $mensaje)
            : redirect()->route('jugadores.index')->with('success', $mensaje);
    }

    public function store(StoreJugadorRequest $request, RegistroJugadorService $registro): JsonResponse|RedirectResponse
    {
        $equipoRuta = $request->route('equipo');
        $equipoId = $equipoRuta instanceof Equipo
            ? $equipoRuta->getKey()
            : (is_numeric($equipoRuta) ? (int) $equipoRuta : $request->validated('equipo_id'));
        $equipo = $equipoRuta instanceof Equipo ? $equipoRuta : Equipo::query()->findOrFail($equipoId);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        if ($usuario->hasRole('Delegado del Club')) {
            Gate::authorize('addPlayer', $equipo);
        }

        /** @var array{persona_id?: int|string|null, dorsal?: int|string|null, nombre?: string, apellidos?: string, fecha_nacimiento?: string, curp?: string, documentos?: array<string, UploadedFile>, fotografia_base64?: string} $datos */
        $datos = $request->validated();
        $jugador = $registro->registrar($equipo, $datos, $usuario);
        $jugador->load(['equipo.grupo.categoria.temporada', 'persona.documentos']);

        $mensaje = 'Jugador inscrito exitosamente.';

        if ($request->routeIs('jugadores.store') && ! $request->expectsJson()) {
            return redirect()->route('jugadores.gestion', $jugador)->with('success', $mensaje);
        }

        return response()->json([
            'mensaje' => $mensaje,
            'inscripcion' => [
                'id' => $jugador->id,
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $jugador->equipo_id,
                'dorsal' => $jugador->dorsal,
                'categoria_id' => $jugador->equipo->grupo->categoria_id,
                'categoria' => $jugador->equipo->grupo->categoria->nombre,
                'temporada_id' => $jugador->equipo->temporada_id,
                'temporada' => $jugador->equipo->grupo->temporada->nombre,
            ],
        ], 201);
    }

    public function vincularForm(): View
    {
        Gate::authorize('link', Jugador::class);

        return view('jugadores.vincular');
    }

    public function vincular(VincularJugadorRequest $request, VinculacionJugadorService $vinculacion): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        $vinculacion->vincular($usuario, (string) $request->validated('curp'));

        return response()->json([
            'mensaje' => 'Si los datos corresponden a un registro elegible, la cuenta quedará vinculada.',
        ]);
    }

    public function show(Jugador $jugador): JsonResponse
    {
        Gate::authorize('view', $jugador);
        $jugador->load(['persona.documentos', 'equipo.grupo.categoria.temporada']);

        return response()->json([
            'jugador' => [
                'id' => $jugador->id,
                'persona' => $jugador->persona,
                'equipo' => $jugador->equipo->nombre,
                'categoria' => $jugador->equipo->grupo->categoria->nombre,
                'temporada' => $jugador->equipo->temporada_id,
                // HU-018, criterio 1: la consulta muestra uno de los 4 estados.
                'estado' => $jugador->estado_elegibilidad,
                'documentos' => $jugador->persona->documentos->map(fn (DocumentoPersona $documento): array => [
                    'id' => $documento->id,
                    'tipo' => $documento->tipo,
                    'descarga' => route('jugadores.documentos.show', [$jugador, $documento]),
                ])->values(),
            ],
        ]);
    }

    /** HU-018: cambio de estado con motivo obligatorio; queda en el historial con quién y por qué. */
    public function cambiarEstado(CambiarEstadoJugadorRequest $request, Jugador $jugador, EstadoJugadorService $estados): JsonResponse|RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        /** @var array{estado_elegibilidad: string, motivo: string, fecha_baja?: string|null} $datos */
        $datos = $request->validated();
        $jugador = $estados->cambiar($jugador, $datos['estado_elegibilidad'], $datos['motivo'], $usuario, $datos['fecha_baja'] ?? null);
        $mensaje = 'El estado del jugador fue actualizado.';

        if (! $request->expectsJson()) {
            return redirect()->route('jugadores.gestion', $jugador)->with('success', $mensaje);
        }

        return response()->json([
            'mensaje' => $mensaje,
            'jugador' => ['id' => $jugador->getKey(), 'estado_elegibilidad' => $jugador->estado_elegibilidad],
        ]);
    }

    public function gestion(Jugador $jugador): View
    {
        Gate::authorize('view', $jugador);
        $jugador->load(['persona.documentos', 'equipo.grupo.categoria.temporada', 'transferenciasOrigen.clubDestino', 'transferenciasOrigen.equipoDestino', 'historialEstados.usuario']);
        $clubId = $jugador->equipo->club_id;
        $temporadaId = $jugador->equipo->temporada_id;
        $equiposRenovacion = Equipo::query()->with(['temporada', 'grupo.categoria'])->where('club_id', $clubId)->whereHas('temporada', fn (Builder $query): Builder => $query->where('estado', 'activa')->whereKeyNot($temporadaId))->get();
        $equiposTransferencia = Equipo::query()->with(['club', 'temporada', 'grupo.categoria'])->where('club_id', '!=', $clubId)->whereHas('temporada', fn (Builder $query): Builder => $query->where('estado', 'activa'))->get();

        return view('jugadores.gestion', compact('jugador', 'equiposRenovacion', 'equiposTransferencia'));
    }

    public function documento(Jugador $jugador, DocumentoPersona $documento): StreamedResponse
    {
        Gate::authorize('view', $jugador);
        abort_unless((int) $documento->persona_id === (int) $jugador->persona_id, 404);
        abort_unless($documento->disco === 'private', 404);

        $extension = pathinfo($documento->ruta_storage, PATHINFO_EXTENSION);
        $nombre = Str::slug($documento->tipo.'-'.$jugador->persona_id).($extension !== '' ? '.'.$extension : '');

        // HU-022 CA1 (RNF-30): ni buscadores ni cachés compartidas guardan el documento
        return Storage::disk('private')->download($documento->ruta_storage, $nombre, [
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * HU-022 CA2: todo cambio a los datos del jugador pide motivo y queda en su historial
     * con usuario, fecha y motivo; el cambio y su registro se guardan en una sola transacción.
     */
    public function update(Request $request, Jugador $jugador, AuditService $auditoria): JsonResponse
    {
        Gate::authorize('update', $jugador);
        $datos = $request->validate([
            'dorsal' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'motivo.required' => 'Escribe el motivo del cambio; quedará en el historial del jugador.',
            'motivo.min' => 'El motivo debe tener al menos 3 caracteres.',
            'motivo.max' => 'El motivo no debe superar los 1000 caracteres.',
        ]);
        $cambios = Arr::except($datos, ['motivo']);

        if ($cambios !== []) {
            $auditoria->conMotivo('Cambio de datos del jugador: '.trim($datos['motivo']), fn () => $jugador->update($cambios));
        }

        return response()->json(['mensaje' => 'Los datos del jugador fueron actualizados.']);
    }

    /** HU-022 CA2: historial de cambios de los datos del jugador, sobre la bitácora de HU-004. */
    public function historial(Jugador $jugador, HistorialCambiosJugadorService $historial): View
    {
        Gate::authorize('view', $jugador);
        $jugador->load(['persona', 'equipo']);

        return view('jugadores.historial', [
            'jugador' => $jugador,
            'registros' => $historial->de($jugador),
            'historial' => $historial,
        ]);
    }

    public function desvincular(Jugador $jugador, VinculacionJugadorService $vinculacion): JsonResponse
    {
        Gate::authorize('unlink', $jugador);
        $vinculacion->desvincular($jugador);

        return response()->json(['mensaje' => 'La cuenta del jugador fue desvinculada.']);
    }

    public function desvincularCuenta(User $usuario, VinculacionJugadorService $vinculacion): JsonResponse
    {
        abort_unless(auth()->user()?->hasRole('Administrador General'), 403);
        $vinculacion->desvincularCuenta($usuario);

        return response()->json(['mensaje' => 'La cuenta del jugador fue desvinculada.']);
    }

}
