<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Http\Requests\VincularJugadorRequest;
use App\Models\DocumentoPersona;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use App\Services\RegistroJugadorService;
use App\Services\VinculacionJugadorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JugadorController extends Controller
{
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
                'documentos' => $jugador->persona->documentos->map(fn (DocumentoPersona $documento): array => [
                    'id' => $documento->id,
                    'tipo' => $documento->tipo,
                    'descarga' => route('jugadores.documentos.show', [$jugador, $documento]),
                ])->values(),
            ],
        ]);
    }

    public function gestion(Jugador $jugador): View
    {
        Gate::authorize('view', $jugador);
        $jugador->load(['persona.documentos', 'equipo.grupo.categoria.temporada']);

        return view('jugadores.gestion', ['jugador' => $jugador]);
    }

    public function documento(Jugador $jugador, DocumentoPersona $documento): StreamedResponse
    {
        Gate::authorize('view', $jugador);
        abort_unless((int) $documento->persona_id === (int) $jugador->persona_id, 404);
        abort_unless($documento->disco === 'private', 404);

        return Storage::disk('private')->download($documento->ruta_storage, $documento->tipo);
    }

    public function update(Request $request, Jugador $jugador): JsonResponse
    {
        Gate::authorize('update', $jugador);
        $datos = $request->validate([
            'dorsal' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
        ]);

        if ($datos !== []) {
            $jugador->update($datos);
        }

        return response()->json(['mensaje' => 'Los datos del jugador fueron actualizados.']);
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
