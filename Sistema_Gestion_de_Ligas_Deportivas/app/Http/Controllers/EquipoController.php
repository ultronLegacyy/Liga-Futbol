<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Http\Requests\StoreEquipoRequest;
use App\Http\Requests\UpdateEquipoRequest;
use App\Services\RegistroEquipoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EquipoController extends Controller
{
    public function store(StoreEquipoRequest $request, RegistroEquipoService $registro): JsonResponse
    {
        $equipo = $registro->registrar($request->user(), $request->validated());
        $equipo->load('grupo.categoria.temporada');

        return response()->json([
            'mensaje' => 'Equipo registrado exitosamente.',
            'equipo' => [
                'id' => $equipo->id,
                'nombre' => $equipo->nombre,
                'club_id' => $equipo->club_id,
                'temporada_id' => $equipo->grupo->temporada_id,
                'categoria_id' => $equipo->grupo->categoria_id,
                'grupo_id' => $equipo->grupo_id,
            ],
        ], 201);
    }

    // Consulta del detalle de un equipo con proteccion contra ID Tampering (RNF-29)
    public function show(Equipo $equipo): JsonResponse
    {
        Gate::authorize('view', $equipo);

        return response()->json([
            'modulo' => 'Equipo Deportivo',
            'equipo_id' => $equipo->id,
            'nombre' => $equipo->nombre,
            'club_id' => $equipo->club_id,
            'acceso' => 'autorizado',
        ]);
    }

    // Actualizacion de datos de un equipo restringida al club propietario
    public function update(UpdateEquipoRequest $request, Equipo $equipo): JsonResponse
    {
        $equipo->update($request->validated());

        return response()->json([
            'mensaje' => 'Equipo actualizado exitosamente.',
            'equipo_id' => $equipo->id,
        ]);
    }
}
