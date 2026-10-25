<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBloqueoCanchaRequest;
use App\Models\BloqueoCancha;
use App\Models\Cancha;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

// Controlador para la gestion de disponibilidad y bloqueos de canchas (HU-024, RF-05.2)
class CanchaBloqueoController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Consultar el listado de bloqueos de una cancha especifica (Blade o JSON).
     */
    public function index(Request $request, Cancha $cancha): View|JsonResponse
    {
        Gate::authorize('view', $cancha);

        $bloqueos = $cancha->bloqueos()
            ->orderBy('fecha_inicio', 'desc')
            ->get();

        if ($request->wantsJson()) {
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

        $ahora = Carbon::now();

        $bloqueosVigentes = $bloqueos->filter(
            fn (BloqueoCancha $b): bool => $b->fecha_fin !== null && $b->fecha_fin->greaterThanOrEqualTo($ahora)
        )->values();

        $bloqueosHistoricos = $bloqueos->filter(
            fn (BloqueoCancha $b): bool => $b->fecha_fin !== null && $b->fecha_fin->lessThan($ahora)
        )->values();

        $bloqueoActivoActual = $bloqueos->first(
            fn (BloqueoCancha $b): bool => $b->fecha_inicio !== null
                && $b->fecha_fin !== null
                && $b->fecha_inicio->lessThanOrEqualTo($ahora)
                && $b->fecha_fin->greaterThanOrEqualTo($ahora)
        );

        $todasCanchas = Cancha::query()
            ->orderBy('nombre')
            ->get();

        return view('canchas.bloqueos.index', [
            'cancha' => $cancha,
            'bloqueos' => $bloqueos,
            'bloqueosVigentes' => $bloqueosVigentes,
            'bloqueosHistoricos' => $bloqueosHistoricos,
            'bloqueoActivoActual' => $bloqueoActivoActual,
            'todasCanchas' => $todasCanchas,
        ]);
    }

    /**
     * Registrar un bloqueo temporal sobre una cancha con motivo obligatorio (HU-024, CA2).
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

        return redirect()->route('canchas.bloqueos.index', $cancha)
            ->with('success', 'Bloqueo de cancha registrado correctamente.');
    }

    /**
     * Retirar o cancelar un bloqueo temporal con registro obligatorio en auditoría (RNF-22, RNF-33).
     */
    public function destroy(Request $request, Cancha $cancha, BloqueoCancha $bloqueo): JsonResponse|RedirectResponse
    {
        if ($bloqueo->cancha_id !== $cancha->id) {
            abort(404, 'El bloqueo no corresponde a la cancha especificada.');
        }

        Gate::authorize('delete', $bloqueo);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'motivo.required' => 'El motivo de la liberación o cancelación es obligatorio.',
            'motivo.string' => 'El motivo debe ser una cadena de texto válida.',
            'motivo.min' => 'El motivo de la liberación debe contener al menos :min caracteres.',
            'motivo.max' => 'El motivo de la liberación no puede exceder :max caracteres.',
        ]);

        $motivoAuditoria = trim((string) $datos['motivo']);

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

        return redirect()->route('canchas.bloqueos.index', $cancha)
            ->with('success', 'Bloqueo de cancha liberado correctamente.');
    }

    /**
     * Endpoint para consultar la disponibilidad de una cancha en un rango horario (HU-024, CA1).
     */
    public function disponibilidad(Request $request, Cancha $cancha): JsonResponse
    {
        Gate::authorize('view', $cancha);

        if ($request->has('fecha') || $request->has('hora_inicio') || $request->has('hora_fin')) {
            $datos = $request->validate([
                'fecha' => ['required', 'date'],
                'hora_inicio' => ['required', 'date_format:H:i,H:i:s'],
                'hora_fin' => ['required', 'date_format:H:i,H:i:s', 'after:hora_inicio'],
            ], [
                'fecha.required' => 'El campo fecha es obligatorio.',
                'fecha.date' => 'El campo fecha debe ser una fecha válida.',
                'hora_inicio.required' => 'El campo hora de inicio es obligatorio.',
                'hora_inicio.date_format' => 'El campo hora de inicio debe tener el formato HH:MM o HH:MM:SS.',
                'hora_fin.required' => 'El campo hora de fin es obligatorio.',
                'hora_fin.date_format' => 'El campo hora de fin debe tener el formato HH:MM o HH:MM:SS.',
                'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            ]);

            $fecha = (string) $datos['fecha'];
            $horaInicio = (string) $datos['hora_inicio'];
            $horaFin = (string) $datos['hora_fin'];

            try {
                $inicio = Carbon::parse("{$fecha} {$horaInicio}");
                $fin = Carbon::parse("{$fecha} {$horaFin}");
                $disponible = $cancha->estaDisponible($fecha, $horaInicio, $horaFin);
            } catch (InvalidArgumentException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['hora_fin' => [$e->getMessage()]],
                ], 422);
            }
        } else {
            $datos = $request->validate([
                'fecha_inicio' => ['required', 'date'],
                'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            ], [
                'fecha_inicio.required' => 'El campo fecha inicio es obligatorio.',
                'fecha_inicio.date' => 'El campo fecha inicio debe ser una fecha válida.',
                'fecha_fin.required' => 'El campo fecha fin es obligatorio.',
                'fecha_fin.date' => 'El campo fecha fin debe ser una fecha válida.',
                'fecha_fin.after' => 'La fecha fin debe ser posterior a la fecha inicio.',
            ]);

            try {
                $inicio = Carbon::parse((string) $datos['fecha_inicio']);
                $fin = Carbon::parse((string) $datos['fecha_fin']);
                $disponible = $cancha->estaDisponible($inicio, $fin);
            } catch (InvalidArgumentException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['fecha_fin' => [$e->getMessage()]],
                ], 422);
            }
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
