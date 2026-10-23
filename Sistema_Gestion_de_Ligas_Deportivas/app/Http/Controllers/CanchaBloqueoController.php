<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBloqueoCanchaRequest;
use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// Controlador para la gestion de disponibilidad y bloqueos de canchas (HU-024, RF-05.2)
class CanchaBloqueoController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Consultar el listado de bloqueos de una cancha especifica.
     */
    public function index(Request $request, Cancha $cancha): JsonResponse
    {
        Gate::authorize('view', $cancha);

        $bloqueos = $cancha->bloqueos()
            ->orderBy('fecha_inicio', 'desc')
            ->get();

        return response()->json([
            'modulo' => 'Bloqueos de Cancha',
            'cancha_id' => $cancha->id,
            'nombre_cancha' => $cancha->nombre,
            'estado_cancha' => $cancha->estado,
            'bloqueos' => $bloqueos->map(
                fn (BloqueoCancha $b): array => $this->datosBloqueo($b)
            )->values(),
        ]);
    }

    /**
     * Registrar un bloqueo temporal sobre una cancha con motivo obligatorio (CA2, CA3, CA4).
     */
    public function store(StoreBloqueoCanchaRequest $request, Cancha $cancha): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', BloqueoCancha::class);

        $datos = $request->validated();
        $motivo = (string) $datos['motivo'];

        $bloqueo = $this->auditService->conMotivo(
            $motivo,
            fn (): BloqueoCancha => $cancha->bloqueos()->create($datos)
        );

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => 'Bloqueo registrado correctamente.',
                'bloqueo' => $this->datosBloqueo($bloqueo),
            ], 201);
        }

        return redirect()->back()
            ->with('success', 'Bloqueo de cancha registrado correctamente.');
    }

    /**
     * Retirar o cancelar un bloqueo temporal con registro obligatorio en auditoria (CA4).
     */
    public function destroy(Request $request, Cancha $cancha, BloqueoCancha $bloqueo): JsonResponse|RedirectResponse
    {
        if ($bloqueo->cancha_id !== $cancha->id) {
            abort(404, 'El bloqueo no corresponde a la cancha especificada.');
        }

        Gate::authorize('delete', $bloqueo);

        $motivo = $request->input('motivo');
        $motivoAuditoria = is_string($motivo) && trim($motivo) !== ''
            ? trim($motivo)
            : "Cancelación o liberación de bloqueo ID {$bloqueo->id} en cancha {$cancha->nombre}";

        $this->auditService->conMotivo(
            $motivoAuditoria,
            function () use ($bloqueo): void {
                $bloqueo->delete();
            }
        );

        if ($request->wantsJson()) {
            return response()->json([
                'mensaje' => 'Bloqueo de cancha liberado correctamente.',
                'bloqueo_id' => $bloqueo->id,
            ]);
        }

        return redirect()->back()
            ->with('success', 'Bloqueo de cancha liberado correctamente.');
    }

    /**
     * Endpoint para consultar la disponibilidad de una cancha en un rango horario (CA1).
     */
    public function disponibilidad(Request $request, Cancha $cancha): JsonResponse
    {
        Gate::authorize('view', $cancha);

        if ($request->filled('fecha') && $request->filled('hora_inicio') && $request->filled('hora_fin')) {
            $request->validate([
                'fecha' => 'required|date',
                'hora_inicio' => 'required|string',
                'hora_fin' => 'required|string',
            ]);

            $fecha = (string) $request->input('fecha');
            $horaInicio = (string) $request->input('hora_inicio');
            $horaFin = (string) $request->input('hora_fin');

            $inicio = Carbon::parse("{$fecha} {$horaInicio}");
            $fin = Carbon::parse("{$fecha} {$horaFin}");
            $disponible = $cancha->estaDisponible($fecha, $horaInicio, $horaFin);
        } else {
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after:fecha_inicio',
            ]);

            $inicio = Carbon::parse((string) $request->input('fecha_inicio'));
            $fin = Carbon::parse((string) $request->input('fecha_fin'));
            $disponible = $cancha->estaDisponible($inicio, $fin);
        }

        return response()->json([
            'cancha_id' => $cancha->id,
            'nombre' => $cancha->nombre,
            'disponible' => $disponible,
            'rango' => [
                'inicio' => $inicio->toDateTimeString(),
                'fin' => $fin->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Estructura de datos serializada de un bloqueo.
     *
     * @return array<string, mixed>
     */
    private function datosBloqueo(BloqueoCancha $bloqueo): array
    {
        return [
            'id' => $bloqueo->id,
            'cancha_id' => $bloqueo->cancha_id,
            'fecha_inicio' => $bloqueo->fecha_inicio?->toDateTimeString(),
            'fecha_fin' => $bloqueo->fecha_fin?->toDateTimeString(),
            'motivo' => $bloqueo->motivo,
            'created_at' => $bloqueo->created_at?->toDateTimeString(),
        ];
    }
}
