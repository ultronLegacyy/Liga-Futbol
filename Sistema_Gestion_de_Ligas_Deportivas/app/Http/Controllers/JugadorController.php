<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Http\Requests\TransferirJugadorRequest;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use App\Services\GestionJugadorService;
use App\Services\RegistroJugadorService;
use Illuminate\Http\JsonResponse;

class JugadorController extends Controller
{
    public function transferir(
        TransferirJugadorRequest $request,
        Jugador $jugador,
        GestionJugadorService $gestion,
    ): JsonResponse {
        $datos = $request->validated();
        $equipoDestino = Equipo::query()->findOrFail($datos['equipo_id']);
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        $historial = $gestion->transferir($jugador, $equipoDestino, $usuario, $datos['dorsal'] ?? null);

        return response()->json([
            'mensaje' => 'La transferencia del jugador fue registrada.',
            'transferencia' => [
                'id' => $historial->getKey(),
                'persona_id' => $historial->persona_id,
                'jugador_origen_id' => $historial->jugador_origen_id,
                'jugador_destino_id' => $historial->jugador_destino_id,
                'club_origen_id' => $historial->club_origen_id,
                'club_destino_id' => $historial->club_destino_id,
                'equipo_origen_id' => $historial->equipo_origen_id,
                'equipo_destino_id' => $historial->equipo_destino_id,
                'temporada_id' => $historial->temporada_id,
            ],
        ], 201);
    }

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
}
