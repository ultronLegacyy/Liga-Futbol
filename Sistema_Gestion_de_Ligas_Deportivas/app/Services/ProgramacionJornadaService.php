<?php

namespace App\Services;

use App\Models\Arbitro;
use App\Models\Cancha;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Partido;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

// Programación de jornadas, alta manual de partidos y gestión de descansos (HU-027, RF-06.1; HU-028, RF-06.2)
class ProgramacionJornadaService
{
    public function __construct(
        private readonly AsignacionCanchaService $canchaService
    ) {
    }

    /**
     * Crear una nueva jornada para el grupo especificado (HU-027, HU-028).
     *
     * @param  array{numero_jornada: int|string, fecha_inicio: string, fecha_fin: string, equipo_descanso_id?: int|string|null}  $datos
     *
     * @throws ValidationException
     */
    public function crearJornada(Grupo $grupo, array $datos): Jornada
    {
        $this->validarTemporadaGrupo($grupo);

        $inicio = Carbon::parse($datos['fecha_inicio']);
        $fin = Carbon::parse($datos['fecha_fin']);

        if ($fin->lessThan($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            ]);
        }

        $numero = (int) $datos['numero_jornada'];
        if ($numero <= 0) {
            throw ValidationException::withMessages([
                'numero_jornada' => 'El número de jornada debe ser un entero positivo.',
            ]);
        }

        $existeNumero = Jornada::query()
            ->where('grupo_id', $grupo->id)
            ->where('numero_jornada', $numero)
            ->exists();

        if ($existeNumero) {
            throw ValidationException::withMessages([
                'numero_jornada' => 'Ya existe una jornada con ese número en este grupo.',
            ]);
        }

        $equipoDescansoId = ! empty($datos['equipo_descanso_id']) ? (int) $datos['equipo_descanso_id'] : null;
        if ($equipoDescansoId !== null) {
            $this->validarEquipoDescanso($grupo, $equipoDescansoId);
        }

        return DB::transaction(function () use ($grupo, $numero, $inicio, $fin, $equipoDescansoId): Jornada {
            return Jornada::create([
                'grupo_id' => $grupo->id,
                'numero_jornada' => $numero,
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $fin->toDateString(),
                'equipo_descanso_id' => $equipoDescansoId,
            ]);
        });
    }

    /**
     * Actualizar una jornada existente (HU-027, HU-028).
     *
     * @param  array{numero_jornada?: int|string, fecha_inicio?: string, fecha_fin?: string, equipo_descanso_id?: int|string|null}  $datos
     *
     * @throws ValidationException
     */
    public function actualizarJornada(Jornada $jornada, array $datos): Jornada
    {
        $this->validarTemporadaGrupo($jornada->grupo);

        $inicio = isset($datos['fecha_inicio']) ? Carbon::parse($datos['fecha_inicio']) : Carbon::parse($jornada->fecha_inicio);
        $fin = isset($datos['fecha_fin']) ? Carbon::parse($datos['fecha_fin']) : Carbon::parse($jornada->fecha_fin);

        if ($fin->lessThan($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            ]);
        }

        if (isset($datos['numero_jornada'])) {
            $numero = (int) $datos['numero_jornada'];
            if ($numero <= 0) {
                throw ValidationException::withMessages([
                    'numero_jornada' => 'El número de jornada debe ser un entero positivo.',
                ]);
            }

            $existeNumero = Jornada::query()
                ->where('grupo_id', $jornada->grupo_id)
                ->where('numero_jornada', $numero)
                ->whereKeyNot($jornada->id)
                ->exists();

            if ($existeNumero) {
                throw ValidationException::withMessages([
                    'numero_jornada' => 'Ya existe otra jornada con ese número en este grupo.',
                ]);
            }
            $jornada->numero_jornada = $numero;
        }

        if (array_key_exists('equipo_descanso_id', $datos)) {
            $equipoDescansoId = ! empty($datos['equipo_descanso_id']) ? (int) $datos['equipo_descanso_id'] : null;
            if ($equipoDescansoId !== null) {
                $this->validarEquipoDescanso($jornada->grupo, $equipoDescansoId, $jornada);
            }
            $jornada->equipo_descanso_id = $equipoDescansoId;
        }

        $jornada->fecha_inicio = $inicio->toDateString();
        $jornada->fecha_fin = $fin->toDateString();

        return DB::transaction(function () use ($jornada): Jornada {
            $jornada->save();

            return $jornada;
        });
    }

    /**
     * Asignar o remover el equipo en descanso de la jornada (HU-028, RF-06.2).
     *
     * @throws ValidationException
     */
    public function asignarDescanso(Jornada $jornada, ?int $equipoId): Jornada
    {
        $this->validarTemporadaGrupo($jornada->grupo);

        return DB::transaction(function () use ($jornada, $equipoId): Jornada {
            $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

            if ($equipoId !== null) {
                $this->validarEquipoDescanso($jornadaBloqueada->grupo, $equipoId, $jornadaBloqueada);
            }

            $jornadaBloqueada->update(['equipo_descanso_id' => $equipoId]);

            return $jornadaBloqueada;
        });
    }

    /**
     * Programar un partido manualmente en la jornada indicada (HU-027, RF-06.1).
     *
     * @param  array{equipo_local_id: int|string, equipo_visitante_id: int|string, cancha_id?: int|string|null, arbitro_id?: int|string|null, fecha_partido?: string|null, hora_partido?: string|null, estado?: string}  $datos
     *
     * @throws ValidationException
     */
    public function programarPartido(Jornada $jornada, array $datos): Partido
    {
        $this->validarTemporadaGrupo($jornada->grupo);

        $localId = (int) $datos['equipo_local_id'];
        $visitanteId = (int) $datos['equipo_visitante_id'];

        if ($localId === $visitanteId) {
            throw ValidationException::withMessages([
                'equipo_visitante_id' => 'El equipo local y el equipo visitante deben ser distintos.',
            ]);
        }

        $local = Equipo::query()->findOrFail($localId);
        $visitante = Equipo::query()->findOrFail($visitanteId);

        if ($local->grupo_id !== $jornada->grupo_id || $visitante->grupo_id !== $jornada->grupo_id) {
            throw ValidationException::withMessages([
                'equipo_local_id' => 'Ambos equipos deben pertenecer al grupo de la jornada.',
            ]);
        }

        // HU-027 CA2: Bloqueo si el equipo tiene sanción vigente que le impide jugar
        if ($this->equipoTieneSancionVigente($local)) {
            throw ValidationException::withMessages([
                'equipo_local_id' => "El equipo {$local->nombre} cuenta con una sanción disciplinaria vigente que le impide ser programado.",
            ]);
        }

        if ($this->equipoTieneSancionVigente($visitante)) {
            throw ValidationException::withMessages([
                'equipo_visitante_id' => "El equipo {$visitante->nombre} cuenta con una sanción disciplinaria vigente que le impide ser programado.",
            ]);
        }

        $canchaId = ! empty($datos['cancha_id']) ? (int) $datos['cancha_id'] : null;
        $arbitroId = ! empty($datos['arbitro_id']) ? (int) $datos['arbitro_id'] : null;
        $fecha = ! empty($datos['fecha_partido']) ? Carbon::parse($datos['fecha_partido'])->toDateString() : null;
        $hora = ! empty($datos['hora_partido']) ? Carbon::parse($datos['hora_partido'])->format('H:i:s') : null;

        if ($canchaId !== null || $arbitroId !== null) {
            if ($fecha === null || $hora === null) {
                throw ValidationException::withMessages([
                    'fecha_partido' => 'Se requiere especificar fecha y hora del partido para asignar cancha o árbitro.',
                ]);
            }
        }

        // HU-027: Validar que la fecha del partido esté comprendida dentro del margen de la jornada
        if ($fecha !== null) {
            $inicioJornada = Carbon::parse($jornada->fecha_inicio)->toDateString();
            $finJornada = Carbon::parse($jornada->fecha_fin)->toDateString();
            if ($fecha < $inicioJornada || $fecha > $finJornada) {
                throw ValidationException::withMessages([
                    'fecha_partido' => "La fecha del partido debe estar comprendida dentro del período de la jornada ({$inicioJornada} a {$finJornada}).",
                ]);
            }
        }

        $cancha = $canchaId !== null ? Cancha::query()->findOrFail($canchaId) : null;
        $arbitro = $arbitroId !== null ? Arbitro::query()->findOrFail($arbitroId) : null;

        $temporada = $jornada->grupo->temporada;

        try {
            return DB::transaction(function () use ($jornada, $local, $visitante, $localId, $visitanteId, $cancha, $arbitro, $fecha, $hora, $temporada, $datos): Partido {
                // Bloquear la fila de la jornada con lockForUpdate() al inicio para serializar la programación concurrente (RNF-33)
                $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

                // HU-028: un equipo que descansa en la jornada no puede ser programado a jugar
                if ($jornadaBloqueada->equipo_descanso_id !== null && in_array($jornadaBloqueada->equipo_descanso_id, [$localId, $visitanteId], true)) {
                    $descansando = ($localId === $jornadaBloqueada->equipo_descanso_id) ? $local->nombre : $visitante->nombre;
                    throw ValidationException::withMessages([
                        'equipo_local_id' => "El equipo {$descansando} está en descanso en esta jornada y no puede ser programado.",
                    ]);
                }

                // HU-027: un equipo no puede jugar dos veces en la misma jornada (evaluado atómicamente bajo el lock)
                $localOcupado = $jornadaBloqueada->partidos()
                    ->where(fn ($q) => $q->where('equipo_local_id', $localId)->orWhere('equipo_visitante_id', $localId))
                    ->exists();

                if ($localOcupado) {
                    throw ValidationException::withMessages([
                        'equipo_local_id' => "El equipo {$local->nombre} ya tiene un partido programado en esta jornada.",
                    ]);
                }

                $visitanteOcupado = $jornadaBloqueada->partidos()
                    ->where(fn ($q) => $q->where('equipo_local_id', $visitanteId)->orWhere('equipo_visitante_id', $visitanteId))
                    ->exists();

                if ($visitanteOcupado) {
                    throw ValidationException::withMessages([
                        'equipo_visitante_id' => "El equipo {$visitante->nombre} ya tiene un partido programado en esta jornada.",
                    ]);
                }

                if ($cancha !== null && $fecha !== null && $hora !== null) {
                    $inicio = Carbon::parse($fecha)->setTimeFromTimeString($hora);
                    $fin = $inicio->copy()->addMinutes($this->duracionPartidoMinutos());

                    // Bloquear fila de cancha para evitar colisión de horario concurrente (HU-025, RNF-33)
                    $canchaBloqueada = Cancha::query()->whereKey($cancha->id)->lockForUpdate()->firstOrFail();
                    $motivoCancha = $this->canchaService->motivoDeRechazo($canchaBloqueada, $inicio, $fin);
                    if ($motivoCancha !== null) {
                        throw ValidationException::withMessages(['cancha_id' => $motivoCancha]);
                    }
                }

                if ($arbitro !== null && $fecha !== null && $hora !== null) {
                    // Bloquear fila de árbitro para ordenar asignaciones concurrentes (HU-026, RNF-33)
                    $arbitroBloqueado = Arbitro::query()->whereKey($arbitro->id)->lockForUpdate()->firstOrFail();

                    if ($temporada !== null && ! $arbitroBloqueado->disponibleEn($temporada)) {
                        throw ValidationException::withMessages([
                            'arbitro_id' => 'El árbitro debe estar activo y tener rol de árbitro en la temporada del partido.',
                        ]);
                    }

                    $arbitroOcupado = Partido::query()
                        ->where('arbitro_id', $arbitroBloqueado->id)
                        ->where('fecha_partido', $fecha)
                        ->where('hora_partido', $hora)
                        ->exists();

                    if ($arbitroOcupado) {
                        throw ValidationException::withMessages([
                            'arbitro_id' => 'El árbitro ya tiene asignado otro partido a la misma fecha y hora.',
                        ]);
                    }
                }

                $estado = $datos['estado'] ?? (($cancha !== null && $fecha !== null && $hora !== null) ? 'programado' : 'borrador');

                return Partido::create([
                    'jornada_id' => $jornadaBloqueada->id,
                    'equipo_local_id' => $localId,
                    'equipo_visitante_id' => $visitanteId,
                    'cancha_id' => $cancha?->id,
                    'arbitro_id' => $arbitro?->id,
                    'fecha_partido' => $fecha,
                    'hora_partido' => $hora,
                    'estado' => $estado,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'arbitro_id' => 'El árbitro ya tiene asignado otro partido a la misma fecha y hora.',
            ]);
        }
    }

    /**
     * Actualizar o reprogramar un partido existente (HU-027, RF-06.1).
     *
     * @param  array{equipo_local_id?: int|string, equipo_visitante_id?: int|string, cancha_id?: int|string|null, arbitro_id?: int|string|null, fecha_partido?: string|null, hora_partido?: string|null, estado?: string}  $datos
     *
     * @throws ValidationException
     */
    public function actualizarPartido(Partido $partido, array $datos): Partido
    {
        $jornada = $partido->jornada;
        $this->validarTemporadaGrupo($jornada->grupo);

        $localId = isset($datos['equipo_local_id']) ? (int) $datos['equipo_local_id'] : $partido->equipo_local_id;
        $visitanteId = isset($datos['equipo_visitante_id']) ? (int) $datos['equipo_visitante_id'] : $partido->equipo_visitante_id;

        if ($localId === $visitanteId) {
            throw ValidationException::withMessages([
                'equipo_visitante_id' => 'El equipo local y el equipo visitante deben ser distintos.',
            ]);
        }

        $local = Equipo::query()->findOrFail($localId);
        $visitante = Equipo::query()->findOrFail($visitanteId);

        if ($local->grupo_id !== $jornada->grupo_id || $visitante->grupo_id !== $jornada->grupo_id) {
            throw ValidationException::withMessages([
                'equipo_local_id' => 'Ambos equipos deben pertenecer al grupo de la jornada.',
            ]);
        }

        $canchaId = array_key_exists('cancha_id', $datos)
            ? (! empty($datos['cancha_id']) ? (int) $datos['cancha_id'] : null)
            : $partido->cancha_id;

        $arbitroId = array_key_exists('arbitro_id', $datos)
            ? (! empty($datos['arbitro_id']) ? (int) $datos['arbitro_id'] : null)
            : $partido->arbitro_id;

        $fecha = array_key_exists('fecha_partido', $datos)
            ? (! empty($datos['fecha_partido']) ? Carbon::parse($datos['fecha_partido'])->toDateString() : null)
            : ($partido->fecha_partido ? Carbon::parse($partido->fecha_partido)->toDateString() : null);

        $hora = array_key_exists('hora_partido', $datos)
            ? (! empty($datos['hora_partido']) ? Carbon::parse($datos['hora_partido'])->format('H:i:s') : null)
            : $partido->hora_partido;

        if ($canchaId !== null || $arbitroId !== null) {
            if ($fecha === null || $hora === null) {
                throw ValidationException::withMessages([
                    'fecha_partido' => 'Se requiere especificar fecha y hora del partido para asignar cancha o árbitro.',
                ]);
            }
        }

        // HU-027: Validar que la fecha del partido esté comprendida dentro del margen de la jornada
        if ($fecha !== null) {
            $inicioJornada = Carbon::parse($jornada->fecha_inicio)->toDateString();
            $finJornada = Carbon::parse($jornada->fecha_fin)->toDateString();
            if ($fecha < $inicioJornada || $fecha > $finJornada) {
                throw ValidationException::withMessages([
                    'fecha_partido' => "La fecha del partido debe estar comprendida dentro del período de la jornada ({$inicioJornada} a {$finJornada}).",
                ]);
            }
        }

        $cancha = $canchaId !== null ? Cancha::query()->findOrFail($canchaId) : null;
        $arbitro = $arbitroId !== null ? Arbitro::query()->findOrFail($arbitroId) : null;
        $temporada = $jornada->grupo->temporada;

        try {
            return DB::transaction(function () use ($partido, $jornada, $local, $visitante, $localId, $visitanteId, $cancha, $arbitro, $fecha, $hora, $temporada, $datos): Partido {
                // Bloquear la jornada con lockForUpdate() al inicio para serializar modificaciones concurrentes (RNF-33)
                $jornadaBloqueada = Jornada::query()->whereKey($jornada->id)->lockForUpdate()->firstOrFail();

                if ($jornadaBloqueada->equipo_descanso_id !== null && in_array($jornadaBloqueada->equipo_descanso_id, [$localId, $visitanteId], true)) {
                    $descansando = ($localId === $jornadaBloqueada->equipo_descanso_id) ? $local->nombre : $visitante->nombre;
                    throw ValidationException::withMessages([
                        'equipo_local_id' => "El equipo {$descansando} está en descanso en esta jornada y no puede ser programado.",
                    ]);
                }

                $localOcupado = $jornadaBloqueada->partidos()
                    ->whereKeyNot($partido->id)
                    ->where(fn ($q) => $q->where('equipo_local_id', $localId)->orWhere('equipo_visitante_id', $localId))
                    ->exists();

                if ($localOcupado) {
                    throw ValidationException::withMessages([
                        'equipo_local_id' => "El equipo {$local->nombre} ya tiene un partido programado en esta jornada.",
                    ]);
                }

                $visitanteOcupado = $jornadaBloqueada->partidos()
                    ->whereKeyNot($partido->id)
                    ->where(fn ($q) => $q->where('equipo_local_id', $visitanteId)->orWhere('equipo_visitante_id', $visitanteId))
                    ->exists();

                if ($visitanteOcupado) {
                    throw ValidationException::withMessages([
                        'equipo_visitante_id' => "El equipo {$visitante->nombre} ya tiene un partido programado en esta jornada.",
                    ]);
                }

                if ($cancha !== null && $fecha !== null && $hora !== null) {
                    $inicio = Carbon::parse($fecha)->setTimeFromTimeString($hora);
                    $fin = $inicio->copy()->addMinutes($this->duracionPartidoMinutos());

                    $canchaBloqueada = Cancha::query()->whereKey($cancha->id)->lockForUpdate()->firstOrFail();
                    $motivoCancha = $this->canchaService->motivoDeRechazo($canchaBloqueada, $inicio, $fin, $partido);
                    if ($motivoCancha !== null) {
                        throw ValidationException::withMessages(['cancha_id' => $motivoCancha]);
                    }
                }

                if ($arbitro !== null && $fecha !== null && $hora !== null) {
                    $arbitroBloqueado = Arbitro::query()->whereKey($arbitro->id)->lockForUpdate()->firstOrFail();

                    if ($temporada !== null && ! $arbitroBloqueado->disponibleEn($temporada)) {
                        throw ValidationException::withMessages([
                            'arbitro_id' => 'El árbitro debe estar activo y tener rol de árbitro en la temporada del partido.',
                        ]);
                    }

                    $arbitroOcupado = Partido::query()
                        ->where('arbitro_id', $arbitroBloqueado->id)
                        ->where('fecha_partido', $fecha)
                        ->where('hora_partido', $hora)
                        ->whereKeyNot($partido->id)
                        ->exists();

                    if ($arbitroOcupado) {
                        throw ValidationException::withMessages([
                            'arbitro_id' => 'El árbitro ya tiene asignado otro partido a la misma fecha y hora.',
                        ]);
                    }
                }

                $estado = $datos['estado'] ?? $partido->estado;

                $partido->update([
                    'equipo_local_id' => $localId,
                    'equipo_visitante_id' => $visitanteId,
                    'cancha_id' => $cancha?->id,
                    'arbitro_id' => $arbitro?->id,
                    'fecha_partido' => $fecha,
                    'hora_partido' => $hora,
                    'estado' => $estado,
                ]);

                return $partido;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'arbitro_id' => 'El árbitro ya tiene asignado otro partido a la misma fecha y hora.',
            ]);
        }
    }

    /**
     * Eliminar un partido programado.
     *
     * @throws ValidationException
     */
    public function eliminarPartido(Partido $partido): void
    {
        $this->validarTemporadaGrupo($partido->jornada->grupo);

        if (in_array($partido->estado, ['finalizado', 'en juego'], true)) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede eliminar un partido que ya se encuentra en juego o ha finalizado.',
            ]);
        }

        DB::transaction(fn () => $partido->delete());
    }

    /**
     * Eliminar una jornada completa.
     *
     * @throws ValidationException
     */
    public function eliminarJornada(Jornada $jornada): void
    {
        $this->validarTemporadaGrupo($jornada->grupo);

        $partidosJugados = $jornada->partidos()
            ->whereIn('estado', ['finalizado', 'en juego'])
            ->exists();

        if ($partidosJugados) {
            throw ValidationException::withMessages([
                'jornada_id' => 'No se puede eliminar una jornada con partidos ya disputados o en juego.',
            ]);
        }

        DB::transaction(fn () => $jornada->delete());
    }

    /**
     * Evalúa si un equipo cuenta con una sanción disciplinaria vigente que le impida jugar (HU-027 CA2).
     */
    public function equipoTieneSancionVigente(Equipo $equipo): bool
    {
        if ($equipo->partidos_default_acumulados >= 3) {
            return true;
        }

        if (Schema::hasTable('sanciones_equipos')) {
            return DB::table('sanciones_equipos')
                ->where('equipo_id', $equipo->id)
                ->where('estado', 'Vigente')
                ->exists();
        }

        return false;
    }

    private function validarTemporadaGrupo(Grupo $grupo): void
    {
        if ($grupo->temporada?->estado === 'cerrada') {
            throw ValidationException::withMessages([
                'grupo_id' => 'Una temporada cerrada no admite cambios en sus jornadas.',
            ]);
        }
    }

    private function validarEquipoDescanso(Grupo $grupo, int $equipoId, ?Jornada $jornada = null): void
    {
        $equipo = Equipo::query()->findOrFail($equipoId);

        if ($equipo->grupo_id !== $grupo->id) {
            throw ValidationException::withMessages([
                'equipo_descanso_id' => 'El equipo en descanso debe pertenecer al grupo de la jornada.',
            ]);
        }

        if ($jornada !== null) {
            $tienePartidos = $jornada->partidos()
                ->where(fn ($q) => $q->where('equipo_local_id', $equipoId)->orWhere('equipo_visitante_id', $equipoId))
                ->exists();

            if ($tienePartidos) {
                throw ValidationException::withMessages([
                    'equipo_descanso_id' => 'El equipo seleccionado ya tiene partidos programados en esta jornada y no puede descansar.',
                ]);
            }
        }
    }

    private function duracionPartidoMinutos(): int
    {
        $minutos = (int) config('programacion.duracion_partido_minutos', 90);

        return $minutos > 0 ? $minutos : 90;
    }
}
