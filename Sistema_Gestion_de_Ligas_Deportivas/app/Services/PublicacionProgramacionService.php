<?php

namespace App\Services;

use App\Models\Jornada;
use App\Models\Partido;
use App\Models\ReprogramacionPartido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Orquestador del flujo de programación semanal y validación de conflictos (HU-030 / RF-06.5 / HU-033 / RNF-19)
class PublicacionProgramacionService
{
    public function __construct(
        private readonly AvisoProgramacionService $avisos,
        private readonly DetectorConflictosProgramacionService $detectorConflictos,
    ) {
    }

    /**
     * Inspecciona una jornada e identifica cualquier conflicto bloqueante de programación.
     *
     * Regla (Obligatorio 11): Los partidos cancelados no generan conflictos en canchas ni en duplicidad de equipos.
     *
     * @return array<int, string> Lista de conflictos encontrados, vacía si no hay colisiones.
     */
    public function detectarConflictos(Jornada $jornada): array
    {
        $detallados = $this->detectarConflictosDetallados($jornada);

        $mensajes = array_map(
            static fn (array $conflicto): string => (string) $conflicto['mensaje'],
            array_filter(
                $detallados,
                static fn (array $conflicto): bool => ($conflicto['severidad'] ?? 'bloqueante') === 'bloqueante'
            )
        );

        return array_values(array_unique($mensajes));
    }

    /**
     * Retorna el diagnóstico detallado (regla origen, causa y sugerencia) de conflictos de la jornada (HU-033 / RNF-19).
     *
     * @param  array<int, string>  $horarios
     * @return array<int, array<string, mixed>>
     */
    public function detectarConflictosDetallados(Jornada $jornada, array $horarios = []): array
    {
        return $this->detectorConflictos->analizarJornada($jornada, $horarios);
    }

    /**
     * Pasa la jornada al estado 'en_revision' para revisión previa a su aprobación.
     *
     * @throws ValidationException
     */
    public function enviarARevision(Jornada $jornada, ?User $usuario = null): Jornada
    {
        return DB::transaction(function () use ($jornada): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

            if ($jornadaBloqueada->estado !== Jornada::ESTADO_BORRADOR) {
                throw ValidationException::withMessages([
                    'jornada' => "Solo una jornada en estado 'borrador' puede enviarse a revisión. Estado actual: '{$jornadaBloqueada->estado}'.",
                ]);
            }

            if ($jornadaBloqueada->partidos()->count() === 0) {
                throw ValidationException::withMessages([
                    'jornada' => 'No se puede enviar a revisión una jornada sin partidos programados.',
                ]);
            }

            $jornadaBloqueada->estado = Jornada::ESTADO_EN_REVISION;
            $jornadaBloqueada->save();

            return $jornadaBloqueada;
        }, 3);
    }

    /**
     * Aprueba la programación semanal.
     *
     * @throws ValidationException
     */
    public function aprobar(Jornada $jornada, ?User $usuario = null): Jornada
    {
        if ($jornada->estado !== Jornada::ESTADO_EN_REVISION) {
            throw ValidationException::withMessages([
                'jornada' => "Solo una jornada en estado 'en_revision' puede ser aprobada. Estado actual: '{$jornada->estado}'.",
            ]);
        }

        $usuarioActuante = $usuario ?? auth()->user();

        return DB::transaction(function () use ($jornada, $usuarioActuante): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

            if ($jornadaBloqueada->estado !== Jornada::ESTADO_EN_REVISION) {
                throw ValidationException::withMessages([
                    'jornada' => "Solo una jornada en estado 'en_revision' puede ser aprobada. Estado actual: '{$jornadaBloqueada->estado}'.",
                ]);
            }

            $jornadaBloqueada->estado = Jornada::ESTADO_APROBADA;
            $jornadaBloqueada->aprobado_por_id = $usuarioActuante?->id;
            $jornadaBloqueada->fecha_aprobacion = now();
            $jornadaBloqueada->save();

            return $jornadaBloqueada;
        }, 3);
    }

    /**
     * Publica la programación semanal. Bloquea si existen conflictos (HU-030 Criterio 2).
     *
     * @throws ValidationException
     */
    public function publicar(Jornada $jornada, ?User $usuario = null): Jornada
    {
        $usuarioActuante = $usuario ?? auth()->user();

        return DB::transaction(function () use ($jornada, $usuarioActuante): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

            if ($jornadaBloqueada->estado !== Jornada::ESTADO_APROBADA) {
                throw ValidationException::withMessages([
                    'jornada' => "Solo una jornada previamente aprobada puede ser publicada. Estado actual: '{$jornadaBloqueada->estado}'.",
                ]);
            }

            $partidosBorrador = $jornadaBloqueada->partidos()
                ->where('estado', Partido::ESTADO_BORRADOR)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Validar conflictos dentro del bloqueo para garantizar consistencia frente a concurrencia
            $conflictos = $this->detectarConflictos($jornadaBloqueada);
            if (! empty($conflictos)) {
                throw ValidationException::withMessages([
                    'publicacion' => 'La programación contiene conflictos no resueltos y no puede publicarse: '.implode('; ', $conflictos),
                    'conflictos' => $conflictos,
                ]);
            }

            $jornadaBloqueada->estado = Jornada::ESTADO_PUBLICADA;
            $jornadaBloqueada->fecha_publicacion = now();
            $jornadaBloqueada->save();

            // Obligatorio 3: Actualizar individualmente cada partido en borrador y registrar en bitácora auditable
            foreach ($partidosBorrador as $partidoBorrador) {
                $partidoBorrador->estado = Partido::ESTADO_PROGRAMADO;
                $partidoBorrador->save();

                if ($usuarioActuante instanceof User) {
                    ReprogramacionPartido::create([
                        'partido_id' => $partidoBorrador->id,
                        'usuario_id' => $usuarioActuante->id,
                        'estado_anterior' => Partido::ESTADO_BORRADOR,
                        'estado_nuevo' => Partido::ESTADO_PROGRAMADO,
                        'cancha_anterior_id' => $partidoBorrador->cancha_id,
                        'cancha_nueva_id' => $partidoBorrador->cancha_id,
                        'fecha_anterior' => $partidoBorrador->fecha_partido,
                        'fecha_nueva' => $partidoBorrador->fecha_partido,
                        'hora_anterior' => $partidoBorrador->hora_partido,
                        'hora_nueva' => $partidoBorrador->hora_partido,
                        'motivo' => 'Publicación oficial de la jornada semanal.',
                    ]);
                }
            }

            // HU-064: aviso a los delegados, al confirmarse la publicación
            $this->avisos->jornadaPublicada($jornadaBloqueada);

            return $jornadaBloqueada;
        }, 3);
    }
}
