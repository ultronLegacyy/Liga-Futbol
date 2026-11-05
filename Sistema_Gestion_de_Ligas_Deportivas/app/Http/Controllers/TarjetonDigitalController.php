<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTarjetonDigitalRequest;
use App\Models\Club;
use App\Models\TarjetonDigital;
use App\Models\Temporada;
use App\Services\TarjetonDigitalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TarjetonDigitalController extends Controller
{
    public function store(
        StoreTarjetonDigitalRequest $request,
        Club $club,
        Temporada $temporada,
        TarjetonDigitalService $servicio,
    ): JsonResponse {
        $usuarioId = auth()->id();
        $tarjeton = $servicio->emitir(
            $club,
            $temporada,
            $usuarioId === null ? null : (int) $usuarioId,
        );
        $nuevo = $tarjeton->wasRecentlyCreated;

        return response()->json([
            'mensaje' => $nuevo ? 'Tarjetón digital generado.' : 'El tarjetón vigente no tuvo cambios.',
            'tarjeton' => $this->datosTarjeton($tarjeton),
        ], $nuevo ? 201 : 200);
    }

    public function index(Club $club, Temporada $temporada): JsonResponse
    {
        Gate::authorize('viewAny', [TarjetonDigital::class, $club, $temporada]);

        $tarjetones = $club->tarjetonesDigitales()
            ->where('temporada_id', $temporada->id)
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'club_id' => $club->id,
            'temporada_id' => $temporada->id,
            'tarjetones' => $tarjetones->map(fn (TarjetonDigital $tarjeton): array => $this->datosTarjeton($tarjeton))->values(),
        ]);
    }

    public function show(Club $club, Temporada $temporada, int $version): JsonResponse
    {
        Gate::authorize('viewAny', [TarjetonDigital::class, $club, $temporada]);

        $tarjeton = $club->tarjetonesDigitales()
            ->where('temporada_id', $temporada->id)
            ->where('version', $version)
            ->firstOrFail();

        Gate::authorize('view', $tarjeton);

        return response()->json(['tarjeton' => $this->datosTarjeton($tarjeton)]);
    }

    /** @return array<string, mixed> */
    private function datosTarjeton(TarjetonDigital $tarjeton): array
    {
        return [
            'id' => $tarjeton->id,
            'club_id' => $tarjeton->club_id,
            'temporada_id' => $tarjeton->temporada_id,
            'version' => $tarjeton->version,
            'generado_en' => Carbon::parse($tarjeton->generado_en)->toIso8601String(),
            'generado_por_usuario_id' => $tarjeton->generado_por_usuario_id,
            'contenido' => $tarjeton->contenido,
        ];
    }
}
