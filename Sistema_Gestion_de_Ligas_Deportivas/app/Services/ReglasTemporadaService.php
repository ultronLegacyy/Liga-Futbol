<?php

namespace App\Services;

use App\Models\ReglaTemporada;
use App\Models\Temporada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReglasTemporadaService
{
    /**
     * Registra una nueva versión de las reglas y su auditoría
     * dentro de una misma transacción.
     *
     * @param array<string, mixed> $reglas
     */
    public function guardar(
        Temporada $temporada,
        array $reglas,
        string $motivo
    ): ReglaTemporada {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo del cambio es obligatorio.',
            ]);
        }

        return DB::transaction(function () use (
            $temporada,
            $reglas,
            $motivo
        ): ReglaTemporada {
            $bloqueada = Temporada::query()
                ->whereKey($temporada->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('update', $bloqueada);

            $versionAnterior = ReglaTemporada::query()
                ->where('temporada_id', $bloqueada->id)
                ->max('version');

            $version = ((int) $versionAnterior) + 1;

            $regla = ReglaTemporada::create([
                'temporada_id' => $bloqueada->id,
                'version' => $version,
                'reglas' => $reglas,
                'usuario_id' => auth()->id(),
                'motivo' => $motivo,
            ]);

            app(AuditService::class)->registrar(
                'crear',
                $regla,
                null,
                $regla->getAttributes(),
                $motivo
            );

            return $regla;
        });
    }

    /**
     * Consulta las versiones sin modificar el historial.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ReglaTemporada>
     */
    public function historial(Temporada $temporada)
    {
        Gate::authorize('view', $temporada);

        return ReglaTemporada::query()
            ->where('temporada_id', $temporada->id)
            ->with('usuario:id,name')
            ->orderByDesc('version')
            ->get();
    }
}