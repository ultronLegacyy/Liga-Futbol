<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Http\Requests\StoreVentaClubRequest;
use App\Services\AuditService;
use App\Services\VentaClubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClubController extends Controller
{
    // Registrar un club junto con su directiva y propietario
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Club::class);

        $datos = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clubes', 'nombre'),
            ],
            'colores' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'string', 'max:255'],
            'fecha_afiliacion' => ['nullable', 'date'],
            'motivo' => ['nullable', 'string', 'max:1000'],

            // Propietario legal del club
            'propietario_usuario_id' => [
                'nullable',
                'integer',
                'exists:usuarios,id',
            ],

            // Datos de la directiva
            'directivos' => ['required', 'array', 'min:1'],
            'directivos.*.nombre' => ['required', 'string', 'max:255'],
            'directivos.*.cargo' => ['required', 'string', 'max:255'],
            'directivos.*.firma_digital_url' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $motivo = trim((string) ($datos['motivo'] ?? ''));

        if ($motivo === '') {
            $motivo = 'Registro inicial del club';
        }

        $club = app(AuditService::class)->conMotivo(
            $motivo,
            function () use ($datos, $motivo): Club {

                // Crear el club
                $club = Club::create([
                    'nombre' => $datos['nombre'],
                    'colores' => $datos['colores'] ?? null,
                    'logo' => $datos['logo'] ?? null,
                    'fecha_afiliacion' => $datos['fecha_afiliacion']
                        ?? now()->toDateString(),
                    'estado' => 'activo',
                    'propietario_usuario_id' =>
                        $datos['propietario_usuario_id'] ?? null,
                ]);

                // Registrar integrantes de la directiva
                foreach ($datos['directivos'] as $directivo) {

                    $nuevoDirectivo = $club->directivos()->create([
                        'nombre' => $directivo['nombre'],
                        'cargo' => $directivo['cargo'],
                        'firma_digital_url' =>
                            $directivo['firma_digital_url'] ?? null,
                    ]);

                    // Registrar auditoría de cada directivo
                    app(AuditService::class)->registrar(
                        'directivo_club_creado',
                        $nuevoDirectivo,
                        null,
                        $nuevoDirectivo->getAttributes(),
                        $motivo
                    );
                }

                return $club;
            }
        );

        return response()->json([
            'mensaje' => 'Club y directiva registrados exitosamente.',
            'club' => $club->load([
                'directivos',
                'propietario',
            ]),
        ], 201);
    }

    // Consulta del perfil del club con protección contra ID Tampering
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

    // Actualización de datos del club
    public function update(Request $request, Club $club): JsonResponse
    {
        Gate::authorize('update', $club);

        $club->update($request->only([
            'nombre',
            'colores',
            'logo',
        ]));

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

    public function vender(StoreVentaClubRequest $request, Club $club, VentaClubService $servicio): JsonResponse
    {
        $datos = $request->validated();
        $historial = $servicio->vender(
            $club,
            (int) $datos['propietario_usuario_id'],
            $datos['fecha_venta'],
            $datos['carta_venta_pdf'] ?? null,
        );

        return response()->json([
            'mensaje' => 'El traspaso del club se registró y su plantilla deportiva fue reiniciada.',
            'venta' => $historial,
        ], 201);
    }
}
