<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePreferenciasGrupoRequest;
use App\Models\Cancha;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\PreferenciaEquipo;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Gestión de preferencias blandas de programación por equipo para el Programador de Partidos (PO / HU-074).
 */
class PreferenciaEquipoController extends Controller
{
    public function edit(Grupo $grupo): View
    {
        Gate::authorize('create', [Jornada::class, $grupo]);

        $grupo->loadMissing([
            'categoria.temporada',
            'equipos' => fn ($q) => $q->with(['club', 'preferencia'])->orderBy('nombre'),
        ]);

        $canchas = Cancha::query()
            ->where('estado', 'Disponible')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'capacidad_partidos_consecutivos']);

        return view('jornadas.preferencias', [
            'grupo' => $grupo,
            'equipos' => $grupo->equipos,
            'canchas' => $canchas,
            'diasSemana' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'],
        ]);
    }

    public function update(
        StorePreferenciasGrupoRequest $request,
        Grupo $grupo,
        AuditService $audit
    ): RedirectResponse|JsonResponse {
        /** @var array<int|string, array{cancha_id_preferida?: int|string|null, dia_preferido?: string|null, hora_preferida?: string|null}> $entrada */
        $entrada = $request->validated('preferencias', []);
        $equipos = $grupo->equipos()->orderBy('id')->get();

        $actualizados = 0;

        $audit->conMotivo(
            "Actualización de preferencias de programación de equipos para el grupo '{$grupo->nombre}'.",
            function () use ($equipos, $entrada, &$actualizados): void {
                /** @var Equipo $equipo */
                foreach ($equipos as $equipo) {
                    if (! array_key_exists((string) $equipo->id, $entrada) && ! array_key_exists($equipo->id, $entrada)) {
                        continue;
                    }

                    $datos = $entrada[$equipo->id] ?? $entrada[(string) $equipo->id] ?? [];
                    $canchaId = isset($datos['cancha_id_preferida']) && $datos['cancha_id_preferida'] !== ''
                        ? (int) $datos['cancha_id_preferida']
                        : null;
                    $dia = isset($datos['dia_preferido']) && trim((string) $datos['dia_preferido']) !== ''
                        ? trim((string) $datos['dia_preferido'])
                        : null;
                    $hora = isset($datos['hora_preferida']) && trim((string) $datos['hora_preferida']) !== ''
                        ? substr(trim((string) $datos['hora_preferida']), 0, 5).':00'
                        : null;

                    if ($canchaId === null && $dia === null && $hora === null) {
                        PreferenciaEquipo::query()->where('equipo_id', $equipo->id)->get()->each->delete();
                        $actualizados++;

                        continue;
                    }

                    PreferenciaEquipo::query()->updateOrCreate(
                        ['equipo_id' => $equipo->id],
                        [
                            'cancha_id_preferida' => $canchaId,
                            'dia_preferido' => $dia,
                            'hora_preferida' => $hora,
                        ]
                    );
                    $actualizados++;
                }
            }
        );

        $mensaje = "Preferencias de horario actualizadas para {$actualizados} equipos del grupo '{$grupo->nombre}'.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $mensaje,
                'actualizados' => $actualizados,
            ]);
        }

        return redirect()
            ->route('grupos.preferencias.edit', $grupo)
            ->with('success', $mensaje);
    }
}
