<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EquipoController extends Controller
{
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
    public function update(Request $request, Equipo $equipo): JsonResponse
    {
        Gate::authorize('update', $equipo);

        $equipo->update($request->only(['nombre']));

        return response()->json([
            'mensaje' => 'Equipo actualizado exitosamente.',
            'equipo_id' => $equipo->id,
        ]);
    }
}
