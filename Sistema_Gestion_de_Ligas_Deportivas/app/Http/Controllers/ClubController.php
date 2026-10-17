<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClubController extends Controller
{
    // Consulta del perfil del club con proteccion estricta contra ID Tampering (RNF-29)
    public function show(Club $club): JsonResponse
    {
        Gate::authorize('view', $club);

        return response()->json([
            'modulo' => 'Club Deportivo',
            'club_id' => $club->id,
            'nombre' => $club->nombre,
            'estado' => $club->estado,
            'acceso' => 'autorizado',
        ]);
    }

    // Actualizacion de datos del club restringida al directivo propietario o administrador
    public function update(Request $request, Club $club): JsonResponse
    {
        Gate::authorize('update', $club);

        $club->update($request->only(['nombre', 'colores', 'logo']));

        return response()->json([
            'mensaje' => 'Club actualizado exitosamente.',
            'club_id' => $club->id,
        ]);
    }

    // Consulta de equipos pertenecientes a un club
    public function equipos(Club $club): JsonResponse
    {
        Gate::authorize('view', $club);

        return response()->json([
            'modulo' => 'Equipos de Club',
            'club_id' => $club->id,
            'equipos' => $club->equipos,
            'acceso' => 'autorizado',
        ]);
    }
}
