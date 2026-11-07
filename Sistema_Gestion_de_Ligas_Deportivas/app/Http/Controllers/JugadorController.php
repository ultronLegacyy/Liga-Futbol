<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Http\Requests\VincularJugadorRequest;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use App\Services\RegistroJugadorService;
use App\Services\VinculacionJugadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class JugadorController extends Controller
{
    public function store(
        StoreJugadorRequest $request,
        Equipo $equipo,
        RegistroJugadorService $registro,
    ): JsonResponse {
        $jugador = $registro->registrar($equipo, $request->validated());

        return response()->json([
            'mensaje' => 'Jugador inscrito exitosamente.',
            'inscripcion' => [
                'id' => $jugador->id,
                'persona_id' => $jugador->persona_id,
                'equipo_id' => $jugador->equipo_id,
                'dorsal' => $jugador->dorsal,
            ],
        ], 201);
    }

    public function vincular(
        VincularJugadorRequest $request,
        VinculacionJugadorService $vinculacion,
    ): JsonResponse {
        $cuenta = $request->user();
        abort_unless($cuenta instanceof User, 401);

        $vinculacion->vincularPorCurp($cuenta, (string) $request->validated('curp'));

        // La respuesta deliberadamente no distingue CURP desconocida, ya vinculada o vinculada ahora.
        return response()->json([
            'mensaje' => 'Si los datos corresponden a un jugador elegible, la cuenta quedará vinculada.',
        ]);
    }

    public function desvincular(User $usuario, VinculacionJugadorService $vinculacion): JsonResponse
    {
        Gate::authorize('unlink', [Jugador::class, $usuario]);
        $vinculacion->desvincular($usuario);

        return response()->json(['mensaje' => 'La cuenta de jugador fue desvinculada.']);
    }
}
