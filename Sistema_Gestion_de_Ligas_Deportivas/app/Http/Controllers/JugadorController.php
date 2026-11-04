<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Models\Equipo;
use App\Services\RegistroJugadorService;
use Illuminate\Http\JsonResponse;

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
}
