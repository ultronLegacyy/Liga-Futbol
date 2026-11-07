<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Models\Equipo;
<<<<<<< HEAD
use App\Models\Jugador;
use App\Models\JugadorDocumento;
use App\Models\User;
use App\Services\VinculacionJugadorService;
=======
use App\Models\User;
>>>>>>> origin/feat/SCRUM-144-formulario-registro-fotografia
use App\Services\RegistroJugadorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
<<<<<<< HEAD
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
=======
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
>>>>>>> origin/feat/SCRUM-144-formulario-registro-fotografia

class JugadorController extends Controller
{
    public function create(Request $request): View
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);
        abort_unless(
            $usuario->can('jugadores.crear')
                || ($usuario->hasRole('Delegado del Club') && $usuario->club_id !== null),
            403,
        );

        $equipos = Equipo::query()
            ->with(['temporada', 'grupo.categoria'])
            ->whereHas('temporada', fn (Builder $consulta): Builder => $consulta->where('estado', 'activa'))
            ->when(
                $usuario->hasRole('Delegado del Club') && $usuario->club_id !== null,
                fn (Builder $consulta): Builder => $consulta->where('club_id', $usuario->club_id)
            )
            ->orderBy('nombre')
            ->get();

        $temporadas = $equipos->pluck('temporada')->unique('id')->values();
        $categorias = $equipos->pluck('grupo.categoria')->unique('id')->values();

        return view('jugadores.create', compact('equipos', 'temporadas', 'categorias'));
    }

    public function store(
        StoreJugadorRequest $request,
        RegistroJugadorService $registro,
<<<<<<< HEAD
    ): JsonResponse {
        /** @var array{persona_id: int|string, dorsal?: int|string|null, documentos?: array<string, \Illuminate\Http\UploadedFile>} $datos */
        $datos = $request->validated();
        $jugador = $registro->registrar($equipo, $datos);
        $jugador->load('equipo.grupo.categoria.temporada');
=======
    ): JsonResponse|RedirectResponse {
        $equipoRuta = $request->route('equipo');
>>>>>>> origin/feat/SCRUM-144-formulario-registro-fotografia

        $equipoId = $equipoRuta instanceof Equipo
            ? $equipoRuta->getKey()
            : (is_numeric($equipoRuta) ? (int) $equipoRuta : $request->validated('equipo_id'));
        $equipo = $equipoRuta instanceof Equipo ? $equipoRuta : Equipo::query()->findOrFail($equipoId);

        /** @var array{persona_id?: int|string|null, dorsal?: int|string|null, nombre?: string, apellidos?: string, fecha_nacimiento?: string, curp?: string, documentos?: array<string, \Illuminate\Http\UploadedFile>, fotografia_base64?: string} $datos */
        $datos = $request->validated();
        $jugador = $registro->registrar($equipo, $datos);

        $respuesta = [
            'mensaje' => 'Jugador inscrito exitosamente.',
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
        ];

        if (! $request->expectsJson()) {
            return redirect()->route('jugadores.create')->with('success', $respuesta['mensaje']);
        }

        return response()->json($respuesta, 201);
    }

    public function vincular(
        \App\Http\Requests\VincularJugadorRequest $request,
        VinculacionJugadorService $vinculacion,
    ): JsonResponse {
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
        $jugador->load(['persona', 'equipo.grupo.categoria.temporada', 'documentos']);

        return response()->json([
            'jugador' => [
                'id' => $jugador->id,
                'persona' => $jugador->persona,
                'equipo' => $jugador->equipo->nombre,
                'categoria' => $jugador->equipo->grupo->categoria->nombre,
                'temporada' => $jugador->equipo->temporada_id,
                'documentos' => $jugador->documentos->map(fn (JugadorDocumento $documento): array => [
                    'id' => $documento->id,
                    'tipo' => $documento->tipo,
                    'descarga' => route('jugadores.documentos.show', [$jugador, $documento]),
                ])->values(),
            ],
        ]);
    }

    public function documento(Jugador $jugador, JugadorDocumento $documento): StreamedResponse
    {
        Gate::authorize('view', $jugador);
        abort_unless((int) $documento->jugador_id === (int) $jugador->getKey(), 404);

        return Storage::disk($documento->disco)->download($documento->ruta_storage, $documento->tipo);
    }

    public function desvincular(Jugador $jugador, VinculacionJugadorService $vinculacion): JsonResponse
    {
        Gate::authorize('unlink', $jugador);
        $vinculacion->desvincular($jugador);

        return response()->json(['mensaje' => 'La cuenta del jugador fue desvinculada.']);
    }
}
