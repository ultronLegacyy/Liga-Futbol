<?php

namespace App\Services;

use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para calcular vigencia de sanciones y administrar el boletín oficial de castigados (HU-046 / RF-10.3, RNF-44).
 */
class VigenciaSancionService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    /**
     * Calcula hasta qué fecha o partido sigue vigente la sanción (HU-046 Criterio 1).
     */
    public function calcularVigencia(Sancion $sancion): Sancion
    {
        $fechaInicio = $sancion->fecha_inicio ?? Carbon::today();
        $sancion->fecha_inicio = Carbon::parse($fechaInicio);

        // Si ya está cumplida, conservar la fecha real de cumplimiento sin reproyectar partidos futuros
        if ($sancion->estaCumplida()) {
            $sancion->save();

            return $sancion;
        }

        // 1. Sanciones por partidos de suspensión
        if ($sancion->partidos_suspendidos > 0 && $sancion->partidosRestantes() > 0) {
            $equipoId = $sancion->equipo_id;

            // Si la sanción es a persona, ubicar el equipo en que compite
            if ($equipoId === null && $sancion->persona_id !== null) {
                // Primero revisar involucrados del expediente
                $involucrado = $sancion->expediente->involucrados()
                    ->where('persona_id', $sancion->persona_id)
                    ->whereNotNull('equipo_id')
                    ->first();

                if ($involucrado !== null) {
                    $equipoId = $involucrado->equipo_id;
                } else {
                    $jugador = Jugador::query()
                        ->where('persona_id', $sancion->persona_id)
                        ->where('estado_elegibilidad', '!=', 'Baja')
                        ->latest('id')
                        ->first();
                    $equipoId = $jugador?->equipo_id;
                }
            }

            if ($equipoId !== null) {
                $partidosRestantes = $sancion->partidosRestantes();
                $partidoOrigenId = $sancion->expediente->partido_id;

                // Proyectar los siguientes N partidos programados del equipo (excluyendo el partido origen de la falta)
                $proximosPartidos = Partido::query()
                    ->where(function ($q) use ($equipoId): void {
                        $q->where('equipo_local_id', $equipoId)
                            ->orWhere('equipo_visitante_id', $equipoId);
                    })
                    ->when($partidoOrigenId !== null, fn ($q) => $q->where('id', '!=', $partidoOrigenId))
                    ->whereNotIn('estado', [Partido::ESTADO_FINALIZADO, Partido::ESTADO_CANCELADO])
                    ->whereNotNull('fecha_partido')
                    ->orderBy('fecha_partido')
                    ->orderBy('hora_partido')
                    ->take($partidosRestantes)
                    ->get();

                if ($proximosPartidos->isNotEmpty()) {
                    /** @var Partido $ultimoPartido */
                    $ultimoPartido = $proximosPartidos->last();
                    $sancion->partido_suspension_hasta_id = $ultimoPartido->id;
                    $sancion->fecha_fin = Carbon::parse($ultimoPartido->fecha_partido);
                } else {
                    // Si aún no hay partidos generados en calendario, estimar 7 días por partido
                    $diasEstimados = $partidosRestantes * 7;
                    $sancion->fecha_fin = Carbon::parse($sancion->fecha_inicio)->addDays($diasEstimados);
                }
            } else {
                $diasEstimados = $sancion->partidosRestantes() * 7;
                $sancion->fecha_fin = Carbon::parse($sancion->fecha_inicio)->addDays($diasEstimados);
            }
        } elseif ($sancion->tipo_sancion === Sancion::TIPO_TEMPORAL && $sancion->fecha_fin === null) {
            // Predeterminado de 30 días si no se especificó fecha_fin
            $sancion->fecha_fin = Carbon::parse($sancion->fecha_inicio)->addDays(30);
        }

        $sancion->save();

        return $sancion;
    }

    /**
     * Computa partidos cumplidos para jugadores o equipos tras la finalización de un partido oficial.
     * Criterio 2: cuando la sanción se cumple, pasa a estado Cumplida y deja de estar activa.
     */
    public function computarPartidoJugado(Partido $partido): void
    {
        $equiposIds = array_filter([$partido->equipo_local_id, $partido->equipo_visitante_id]);

        if (empty($equiposIds)) {
            return;
        }

        DB::transaction(function () use ($equiposIds, $partido): void {
            // 1. Sanciones a equipos participantes (sanciones colectivas donde no hay persona individual)
            $sancionesEquipos = Sancion::query()
                ->with('expediente')
                ->whereNull('persona_id')
                ->whereIn('equipo_id', $equiposIds)
                ->where('estado', Sancion::ESTADO_VIGENTE)
                ->where('partidos_suspendidos', '>', 0)
                ->whereHas('expediente', fn ($q) => $q->where('partido_id', '!=', $partido->id))
                ->lockForUpdate()
                ->get();

            foreach ($sancionesEquipos as $sancion) {
                $this->aplicarDescuentoPartido($sancion, $partido);
            }

            // 2. Sanciones a jugadores con inscripción activa en los equipos participantes
            $personasIds = Jugador::query()
                ->whereIn('equipo_id', $equiposIds)
                ->where('estado_elegibilidad', '!=', 'Baja')
                ->pluck('persona_id')
                ->filter()
                ->unique()
                ->toArray();

            if (! empty($personasIds)) {
                $sancionesJugadores = Sancion::query()
                    ->with('expediente')
                    ->whereIn('persona_id', $personasIds)
                    ->where('estado', Sancion::ESTADO_VIGENTE)
                    ->where('partidos_suspendidos', '>', 0)
                    ->whereHas('expediente', fn ($q) => $q->where('partido_id', '!=', $partido->id))
                    ->lockForUpdate()
                    ->get();

                foreach ($sancionesJugadores as $sancion) {
                    $this->aplicarDescuentoPartido($sancion, $partido);
                }
            }
        });
    }

    /**
     * Valida formalmente una sanción dictaminada por la Comisión Disciplinaria (RNF-44).
     */
    public function validarSancion(Sancion $sancion, User $usuario, string $motivo): Sancion
    {
        return $this->auditService->conMotivo($motivo, function () use ($sancion, $usuario): Sancion {
            $sancion->validado_por_usuario_id = $usuario->id;
            $sancion->fecha_validacion = now();
            $sancion->save();

            return $sancion;
        });
    }

    /**
     * Publica una o varias sanciones previamente validadas en el boletín semanal de castigados (RNF-44).
     * Toda publicación oficial registra de forma inalterable quién la autorizó y bajo qué cargo.
     *
     * @param  array<int>  $sancionesIds
     * @return int Cantidad de sanciones publicadas
     *
     * @throws ValidationException Si se intenta publicar una sanción aún no validada por la Comisión
     */
    public function publicarEnBoletin(array $sancionesIds, User $usuario, string $motivo, ?string $autorizadoPorCargo = null): int
    {
        return $this->auditService->conMotivo($motivo, function () use ($sancionesIds, $usuario, $autorizadoPorCargo): int {
            $sanciones = Sancion::query()
                ->whereIn('id', $sancionesIds)
                ->where('estado', Sancion::ESTADO_VIGENTE)
                ->get();

            if ($sanciones->isEmpty()) {
                throw ValidationException::withMessages([
                    'sanciones_ids' => 'No se encontraron sanciones vigentes seleccionadas para publicar en el boletín.',
                ]);
            }

            foreach ($sanciones as $sancion) {
                // RNF-44: Un expediente/sanción sin validar por la Comisión Disciplinaria no puede publicarse
                if (! $sancion->estaValidada()) {
                    throw ValidationException::withMessages([
                        'sanciones_ids' => "RNF-44: La sanción #{$sancion->id} aún no ha sido validada formalmente por la Comisión Disciplinaria y no puede publicarse.",
                    ]);
                }
            }

            $cargo = ($autorizadoPorCargo !== null && trim($autorizadoPorCargo) !== '')
                ? trim($autorizadoPorCargo)
                : ($usuario->getRoleNames()->first() ?? 'Comisión Disciplinaria');

            $publicadas = 0;
            foreach ($sanciones as $sancion) {
                $sancion->publicada_en_boletin = true;
                $sancion->autorizado_por_usuario_id = $usuario->id;
                $sancion->autorizado_por_cargo = $cargo;
                $sancion->fecha_publicacion = now();
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
            $sancion->publicada_en_boletin = false;
            $sancion->save();

            return $sancion;
        });
    }

    /**
     * Obtiene las sanciones activas para el boletín oficial de castigados (HU-046 Criterios 2 y 3, RNF-44).
     *
     * Reglas estrictas:
     * 1. Solo sanciones con estado 'Vigente' (las cumplidas ya NO aparecen - Criterio 2).
     * 2. Solo sanciones formalmente validadas y publicadas por la Comisión (RNF-44 - Criterio 3).
     *
     * @return Collection<int, Sancion>
     */
    public function obtenerBoletinCastigados(?int $temporadaId = null): Collection
    {
        $query = Sancion::query()
            ->with([
                'expediente.partido.local:id,nombre',
                'expediente.partido.visitante:id,nombre',
                'expediente.partido.jornada:id,numero_jornada',
                'persona:id,nombre,apellidos',
                'equipo:id,nombre',
                'articulo:id,codigo,descripcion',
                'partidoHasta.local:id,nombre',
                'partidoHasta.visitante:id,nombre',
                'autorizadoPor:id,name,email',
                'validadoPor:id,name,email',
            ])
            ->where('estado', Sancion::ESTADO_VIGENTE)
            ->where('publicada_en_boletin', true)
            ->whereNotNull('validado_por_usuario_id');

        if ($temporadaId !== null) {
            $query->whereHas('expediente.partido.jornada.grupo', function ($q) use ($temporadaId): void {
                $q->where('temporada_id', $temporadaId)
                    ->orWhereHas('categoria', fn ($cq) => $cq->where('temporada_id', $temporadaId));
            });
        }

        return $query->orderByDesc('fecha_publicacion')->get();
    }

    /**
     * Incrementa el cómputo de un partido cumplido y cierra la sanción si completó su vigencia.
     */
    private function aplicarDescuentoPartido(Sancion $sancion, Partido $partido): void
    {
        if ($partido->fecha_partido !== null && $sancion->fecha_inicio !== null && $partido->fecha_partido->lt($sancion->fecha_inicio)) {
            return;
        }

        $sancion->partidos_cumplidos += 1;

        if ($sancion->partidos_cumplidos >= $sancion->partidos_suspendidos) {
            $sancion->estado = Sancion::ESTADO_CUMPLIDA;
            $sancion->partido_suspension_hasta_id = $partido->id;
            $sancion->fecha_fin = $partido->fecha_partido !== null
                ? Carbon::parse($partido->fecha_partido)
                : Carbon::today();
            $sancion->save();

            return;
        }

        $this->calcularVigencia($sancion);
    }
}
