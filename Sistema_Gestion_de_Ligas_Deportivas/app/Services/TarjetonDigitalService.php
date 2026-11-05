<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\TarjetonDigital;
use App\Models\Temporada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TarjetonDigitalService
{
    public function emitir(Club $club, Temporada $temporada, ?int $usuarioId = null): TarjetonDigital
    {
        if ($temporada->estado !== 'activa') {
            throw ValidationException::withMessages([
                'temporada_id' => 'El tarjetón solo puede generarse para una temporada activa.',
            ]);
        }

        return DB::transaction(function () use ($club, $temporada, $usuarioId): TarjetonDigital {
            $clubBloqueado = Club::query()->lockForUpdate()->findOrFail($club->getKey());
            $ultimoTarjeton = $clubBloqueado->tarjetonesDigitales()
                ->where('temporada_id', $temporada->getKey())
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            $contenido = $this->crearContenido($clubBloqueado, $temporada);

            if ($ultimoTarjeton !== null && $ultimoTarjeton->contenido === $contenido) {
                return $ultimoTarjeton;
            }

            return $clubBloqueado->tarjetonesDigitales()->create([
                'temporada_id' => $temporada->getKey(),
                'version' => $ultimoTarjeton === null ? 1 : $ultimoTarjeton->version + 1,
                'contenido' => $contenido,
                'generado_en' => now(),
                'generado_por_usuario_id' => $usuarioId,
            ]);
        });
    }

    public function reemitirParaClub(int $clubId): void
    {
        $club = Club::query()->find($clubId);

        if ($club === null) {
            return;
        }

        $temporadasConTarjeton = $club->tarjetonesDigitales()
            ->select('temporada_id')
            ->distinct()
            ->pluck('temporada_id');

        foreach ($temporadasConTarjeton as $temporadaId) {
            $temporada = Temporada::query()->find((int) $temporadaId);

            if ($temporada?->estado === 'activa') {
                $this->emitir($club, $temporada, auth()->id() === null ? null : (int) auth()->id());
            }
        }
    }

    /**
     * @return array{
     *     club: array{id: int, nombre: string, logo: ?string, colores: ?string, fecha_afiliacion: ?string, estado: string},
     *     directiva: list<array{id: int, nombre: string, cargo: string, firma_digital_url: ?string}>,
     *     equipos: list<array{id: int, nombre: string, categoria: array{id: int, nombre: string, rama: ?string}, grupo: array{id: int, nombre: string} }>
     * }
     */
    private function crearContenido(Club $club, Temporada $temporada): array
    {
        $directiva = $club->directivos()
            ->orderBy('cargo')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($directivo): array => [
                'id' => (int) $directivo->id,
                'nombre' => $directivo->nombre,
                'cargo' => $directivo->cargo,
                'firma_digital_url' => $directivo->firma_digital_url,
            ])
            ->values()
            ->all();

        $equipos = $club->equipos()
            ->whereHas('grupo', fn (Builder $consulta) => $consulta->where('temporada_id', $temporada->getKey()))
            ->with('grupo.categoria')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Equipo $equipo): array => [
                'id' => (int) $equipo->id,
                'nombre' => $equipo->nombre,
                'categoria' => [
                    'id' => (int) $equipo->grupo->categoria->id,
                    'nombre' => $equipo->grupo->categoria->nombre,
                    'rama' => $equipo->grupo->categoria->rama,
                ],
                'grupo' => [
                    'id' => (int) $equipo->grupo->id,
                    'nombre' => $equipo->grupo->nombre,
                ],
            ])
            ->values()
            ->all();

        return [
            'club' => [
                'id' => (int) $club->id,
                'nombre' => $club->nombre,
                'logo' => $club->logo,
                'colores' => $club->colores,
                'fecha_afiliacion' => $club->fecha_afiliacion,
                'estado' => $club->estado,
            ],
            'directiva' => $directiva,
            'equipos' => $equipos,
        ];
    }
}
