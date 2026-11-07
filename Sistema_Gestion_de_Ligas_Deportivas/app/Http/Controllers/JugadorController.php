<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJugadorRequest;
use App\Http\Requests\BajaJugadorRequest;
use App\Http\Requests\RenovarJugadorRequest;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Services\RegistroJugadorService;
use App\Services\GestionJugadorService;
use Illuminate\Http\JsonResponse;

class JugadorController extends Controller
{
    public function darDeBaja(BajaJugadorRequest $request, Jugador $jugador, GestionJugadorService $gestion): JsonResponse
    {
        $datos = $request->validated();
        $jugador = $gestion->darDeBaja($jugador, $datos['motivo'], $datos['fecha_baja']);

        return response()->json([
            'mensaje' => 'El jugador fue dado de baja.',
            'jugador' => [
                'id' => $jugador->getKey(),
                'estado' => $jugador->estado_elegibilidad,
                'motivo_baja' => $jugador->motivo_baja,
                'fecha_baja' => $jugador->fecha_baja?->toDateString(),
            ],
        ]);
    }

    public function renovar(RenovarJugadorRequest $request, Jugador $jugador, GestionJugadorService $gestion): JsonResponse
    {
        $datos = $request->validated();
        $equipo = Equipo::query()->findOrFail($datos['equipo_id']);
        $renovacion = $gestion->renovar($jugador, $equipo, $datos['dorsal'] ?? null);

        return response()->json([
            'mensaje' => 'La inscripción del jugador fue renovada.',
            'inscripcion' => [
                'id' => $renovacion->getKey(),
                'persona_id' => $renovacion->persona_id,
                'equipo_id' => $renovacion->equipo_id,
                'temporada_id' => $equipo->temporada_id,
                'dorsal' => $renovacion->dorsal,
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
