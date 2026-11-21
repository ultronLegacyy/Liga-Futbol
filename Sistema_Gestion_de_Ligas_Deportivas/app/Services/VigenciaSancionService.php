<?php

namespace App\Services;

use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para calcular vigencia de sanciones y administrar el boletín interno de castigados (HU-046 / RF-10.3, RNF-44).
 */
class VigenciaSancionService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Normaliza y actualiza el estado de vigencia de la sanción según su tipo, sin plazos por omisión ni estimaciones (PO #47).
     */
    public function calcularVigencia(Sancion $sancion): Sancion
    {
        $fechaInicio = $sancion->fecha_inicio !== null
            ? $sancion->fecha_inicio->toDateString()
            : Carbon::today(Sancion::ZONA_HORARIA)->toDateString();

        $sancion->fecha_inicio = Carbon::parse($fechaInicio, Sancion::ZONA_HORARIA);

        if ($sancion->estaCumplida() || $sancion->estado === Sancion::ESTADO_CANCELADA) {
            $sancion->save();

            return $sancion;
        }

        if ($sancion->tipo_sancion === Sancion::TIPO_PARTIDOS) {
            // Una suspensión por partidos se cumple contando partidos oficiales reales; no se estima fecha_fin por calendario (PO #47).
            $sancion->partido_suspension_hasta_id = null;
            $sancion->fecha_fin = null;

            if ($sancion->partidos_suspendidos > 0 && $sancion->partidos_cumplidos >= $sancion->partidos_suspendidos) {
                $sancion->estado = Sancion::ESTADO_CUMPLIDA;
            }
        } elseif ($sancion->tipo_sancion === Sancion::TIPO_TEMPORAL && $sancion->fecha_fin !== null) {
            $hoy = Carbon::today(Sancion::ZONA_HORARIA)->toDateString();
            if ($sancion->fecha_fin->toDateString() < $hoy) {
                $sancion->estado = Sancion::ESTADO_CUMPLIDA;
            }
        }

        $sancion->save();

        return $sancion;
    }

    /**
     * Computa partidos cumplidos para jugadores sancionados tras la finalización de un partido oficial.
     * Las sanciones activas de temporadas previas siguen asociadas a persona_id y se descuentan en el equipo actual (PO #49).
     */
    public function computarPartidoJugado(Partido $partido): void
    {
        $equiposIds = array_filter([$partido->equipo_local_id, $partido->equipo_visitante_id]);

        if (empty($equiposIds)) {
            return;
        }

        DB::transaction(function () use ($equiposIds, $partido): void {
            $personasIds = Jugador::query()
                ->whereIn('equipo_id', $equiposIds)
                ->where('estado_elegibilidad', '!=', 'Baja')
                ->pluck('persona_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($personasIds)) {
                return;
            }

            $sancionesJugadores = Sancion::query()
                ->with('expediente')
                ->whereIn('persona_id', $personasIds)
                ->where('tipo_sancion', Sancion::TIPO_PARTIDOS)
                ->where('estado', Sancion::ESTADO_VIGENTE)
                ->where('partidos_suspendidos', '>', 0)
                ->whereHas('expediente', function (Builder $q) use ($partido): void {
                    // Incluye expedientes creados manualmente sin partido_id (evita que partido_id != X ignore NULL en SQL).
                    $q->where(function (Builder $eq) use ($partido): void {
                        $eq->whereNull('partido_id')
                            ->orWhere('partido_id', '!=', $partido->id);
                    });
                })
                ->lockForUpdate()
                ->get();

            foreach ($sancionesJugadores as $sancion) {
                $this->aplicarDescuentoPartido($sancion, $partido);
            }
        });
    }

    /**
     * Valida formalmente una sanción vigente dictaminada por la Comisión Disciplinaria (RNF-44).
     */
    public function validarSancion(Sancion $sancion, User $usuario, string $motivo): Sancion
    {
        return $this->auditService->conMotivo($motivo, function () use ($sancion, $usuario): Sancion {
            $bloqueada = Sancion::query()
                ->lockForUpdate()
                ->findOrFail($sancion->id);

            if (! $bloqueada->estaVigente()) {
                throw ValidationException::withMessages([
                    'sancion' => 'Solo se pueden validar sanciones disciplinarias que se encuentren vigentes.',
                ]);
            }

            if ($bloqueada->estaValidada()) {
                throw ValidationException::withMessages([
                    'sancion' => 'RNF-44: Esta sanción ya fue validada formalmente y su validador registrado es inmutable.',
                ]);
            }

            $bloqueada->validado_por_usuario_id = $usuario->id;
            $bloqueada->fecha_validacion = now();
            $bloqueada->save();

            return $bloqueada;
        });
    }

    /**
     * Publica una o varias sanciones previamente validadas en el boletín oficial de castigados (RNF-44).
     * Toda publicación oficial registra de forma inalterable al usuario autorizador y el cargo derivado de su rol.
     *
     * @param  array<int>  $sancionesIds
     * @return int Cantidad de sanciones publicadas
     */
    public function publicarEnBoletin(array $sancionesIds, User $usuario, string $motivo): int
    {
        return $this->auditService->conMotivo($motivo, function () use ($sancionesIds, $usuario): int {
            $idsOrdenados = array_values(array_unique(array_map('intval', $sancionesIds)));
            sort($idsOrdenados);

            $sanciones = Sancion::query()
                ->whereIn('id', $idsOrdenados)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($sanciones->isEmpty()) {
                throw ValidationException::withMessages([
                    'sanciones_ids' => 'No se encontraron sanciones seleccionadas para publicar en el boletín.',
                ]);
            }

            $hoy = Carbon::today(Sancion::ZONA_HORARIA)->toDateString();

            foreach ($sanciones as $sancion) {
                if (! $sancion->estaVigente()) {
                    throw ValidationException::withMessages([
                        'sanciones_ids' => "La sanción #{$sancion->id} no se encuentra vigente para publicarse en el boletín.",
                    ]);
                }

                if ($sancion->tipo_sancion === Sancion::TIPO_TEMPORAL && $sancion->fecha_fin !== null && $sancion->fecha_fin->toDateString() < $hoy) {
                    throw ValidationException::withMessages([
                        'sanciones_ids' => "La sanción #{$sancion->id} ya concluyó su periodo de vigencia temporal.",
                    ]);
                }

                if (! $sancion->estaValidada()) {
                    throw ValidationException::withMessages([
                        'sanciones_ids' => "RNF-44: La sanción #{$sancion->id} aún no ha sido validada formalmente por la Comisión Disciplinaria y no puede publicarse.",
                    ]);
                }

                if ($sancion->publicada_en_boletin) {
                    throw ValidationException::withMessages([
                        'sanciones_ids' => "RNF-44: La sanción #{$sancion->id} ya se encuentra publicada en el boletín y su autorizador es inmutable.",
                    ]);
                }
            }

            $cargo = $usuario->getRoleNames()->first() ?? 'Comisión Disciplinaria';
            $ahora = now();

            $publicadas = 0;
            foreach ($sanciones as $sancion) {
                $sancion->publicada_en_boletin = true;
                $sancion->autorizado_por_usuario_id = $usuario->id;
                $sancion->autorizado_por_cargo = $cargo;
                $sancion->fecha_publicacion = $ahora;
                $sancion->save();

                $publicadas++;
            }

            return $publicadas;
        });
    }

    /**
     * Retira una sanción del boletín de castigados.
     */
    public function despublicarDeBoletin(Sancion $sancion, User $usuario, string $motivo): Sancion
    {
        return $this->auditService->conMotivo($motivo, function () use ($sancion): Sancion {
            $bloqueada = Sancion::query()
                ->lockForUpdate()
                ->findOrFail($sancion->id);

            if (! $bloqueada->publicada_en_boletin) {
                throw ValidationException::withMessages([
                    'sancion' => 'La sanción indicada no se encuentra publicada en el boletín oficial.',
                ]);
            }

            $bloqueada->publicada_en_boletin = false;
            $bloqueada->save();

            return $bloqueada;
        });
    }

    /**
     * Obtiene las sanciones vigentes activas para el boletín de castigados (HU-046 Criterios 2 y 3, RNF-44, PO #45).
     *
     * Usa la misma vigencia que scopeInhabilitantes() en hora de Ciudad de México para excluir
     * suspensiones temporales vencidas, partidos ya cumplidos, multas y amonestaciones.
     *
     * @return Collection<int, Sancion>
     */
    public function obtenerBoletinCastigados(?int $temporadaId = null, ?User $usuario = null): Collection
    {
        $query = $this->baseQueryBoletin($temporadaId)
            ->inhabilitantes();

        if ($usuario === null || $usuario->hasRole('Administrador General') || $usuario->can('disciplina.sancionar')) {
            $query->publicadasEnBoletin();
        } elseif ($usuario->hasAnyRole(['Presidente del Club', 'Delegado del Club'])) {
            // PO #45: Presidente y Delegado del Club ven las sanciones validadas de su club.
            $clubId = $usuario->club_id;
            if ($clubId === null) {
                return new Collection();
            }

            $query->whereNotNull('validado_por_usuario_id')
                ->where(function (Builder $scope) use ($clubId): void {
                    $scope->whereHas('equipo', fn (Builder $eq) => $eq->where('club_id', $clubId))
                        ->orWhereHas('persona.inscripciones', function (Builder $jq) use ($clubId): void {
                            $jq->where('estado_elegibilidad', '!=', 'Baja')
                                ->whereHas('equipo', fn (Builder $eq) => $eq->where('club_id', $clubId));
                        });
                });
        } elseif ($usuario->hasRole('Jugador')) {
            // PO #45: El jugador ve únicamente sus propias sanciones validadas.
            $personaId = $usuario->persona_id;
            if ($personaId === null) {
                return new Collection();
            }

            $query->whereNotNull('validado_por_usuario_id')
                ->where('persona_id', $personaId);
        } else {
            return new Collection();
        }

        return $query->orderByDesc('fecha_publicacion')
            ->orderByDesc('fecha_validacion')
            ->get();
    }

    /**
     * Obtiene sanciones vigentes pendientes de publicar (solo para Tribunal de Penas / Administrador General).
     *
     * @return Collection<int, Sancion>
     */
    public function obtenerSancionesPendientesBoletin(?int $temporadaId = null): Collection
    {
        return $this->baseQueryBoletin($temporadaId)
            ->inhabilitantes()
            ->where('publicada_en_boletin', false)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Builder<Sancion>
     */
    private function baseQueryBoletin(?int $temporadaId = null): Builder
    {
        $query = Sancion::query()
            ->with([
                'expediente.partido.local:id,nombre',
                'expediente.partido.visitante:id,nombre',
                'expediente.partido.jornada:id,numero_jornada',
                'persona:id,nombre,apellidos',
                'equipo:id,club_id,nombre',
                'articulo:id,codigo,descripcion',
                'partidoHasta.jornada:id,numero_jornada',
                'partidoHasta.local:id,nombre',
                'partidoHasta.visitante:id,nombre',
                'autorizadoPor:id,name,email',
                'validadoPor:id,name,email',
            ]);

        if ($temporadaId !== null) {
            $query->whereHas('expediente.partido.jornada.grupo', function (Builder $q) use ($temporadaId): void {
                $q->where('temporada_id', $temporadaId)
                    ->orWhereHas('categoria', fn (Builder $cq) => $cq->where('temporada_id', $temporadaId));
            });
        }

        return $query;
    }

    /**
     * Incrementa el cómputo de un partido cumplido y cierra la sanción si completó su vigencia.
     */
    private function aplicarDescuentoPartido(Sancion $sancion, Partido $partido): void
    {
        if ($partido->fecha_partido !== null && $sancion->fecha_inicio !== null && $partido->fecha_partido->toDateString() < $sancion->fecha_inicio->toDateString()) {
            return;
        }

        $sancion->partidos_cumplidos += 1;

        if ($sancion->partidos_cumplidos >= $sancion->partidos_suspendidos) {
            $sancion->estado = Sancion::ESTADO_CUMPLIDA;
            $sancion->partido_suspension_hasta_id = $partido->id;
            $sancion->fecha_fin = $partido->fecha_partido !== null
                ? Carbon::parse($partido->fecha_partido->toDateString(), Sancion::ZONA_HORARIA)
                : Carbon::today(Sancion::ZONA_HORARIA);
            $sancion->save();

            return;
        }

        $this->calcularVigencia($sancion);
    }
}
