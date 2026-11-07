<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\JugadorDocumento;
use App\Models\User;
use App\Services\VinculacionJugadorService;
use App\Services\RegistroJugadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JugadorController extends Controller
{
    public function store(
        StoreJugadorRequest $request,
        Equipo $equipo,
        RegistroJugadorService $registro,
    ): JsonResponse {
        /** @var array{persona_id: int|string, dorsal?: int|string|null, documentos?: array<string, \Illuminate\Http\UploadedFile>} $datos */
        $datos = $request->validated();
        $jugador = $registro->registrar($equipo, $datos);
        $jugador->load('equipo.grupo.categoria.temporada');

        return response()->json([
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
        ], 201);
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
