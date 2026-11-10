<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\HistorialTransferenciaJugador;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Altas, bajas, renovaciones y transferencias de jugadores (HU-017, RF-04.2).
 *
 * Las tres operaciones bloquean en el mismo orden (club, equipo, usuario, persona,
 * inscripción) para evitar interbloqueos entre peticiones simultáneas.
 */
class GestionJugadorService
{
    /** Zona horaria de la Liga para «hoy» (igual que CargoFinanciero, HU-051). */
    public const ZONA_HORARIA = 'America/Mexico_City';

    public function __construct(
        private AuditService $auditoria,
        private ReglaSaltoCategoriaService $reglaSaltoCategoria,
        private ReglaEdadCategoriaService $reglaEdad,
    ) {
    }

    public static function hoy(): string
    {
        return now(self::ZONA_HORARIA)->toDateString();
    }

    /** HU-017, criterio 1: baja con motivo y fecha. */
    public function darDeBaja(Jugador $jugador, string $motivo, string $fecha, User $usuario): Jugador
    {
        $id = (int) $jugador->getKey();

        return $this->auditoria->conMotivo('Baja de jugador: '.trim($motivo), function () use ($id, $motivo, $fecha, $usuario): Jugador {
            [$jugador, , $usuario] = $this->bloquearContexto($id, [], $usuario);
            Gate::forUser($usuario)->authorize('deactivate', $jugador);
            $this->aplicarBaja($jugador, $motivo, $fecha);

            return $jugador;
        });
    }

    /**
     * Regla única de baja. Exige la inscripción ya bloqueada con bloquearContexto().
     * El trait Auditable de Jugador deja la auditoría con el motivo de conMotivo().
     */
    public function aplicarBaja(Jugador $jugador, string $motivo, string $fecha): void
    {
        if ($jugador->estado_elegibilidad !== 'Activo') {
            throw ValidationException::withMessages(['jugador' => 'Solo se puede dar de baja a un jugador activo.']);
        }

        $temporada = $this->temporadaDe($jugador);

        // HU-005, criterio 3: una temporada cerrada solo se consulta.
        if ($temporada->estado !== 'activa') {
            throw ValidationException::withMessages(['jugador' => 'Solo se puede dar de baja una inscripción de la temporada activa; la temporada '.$temporada->nombre.' solo se consulta.']);
        }

        $inicio = Carbon::parse($temporada->fecha_inicio)->toDateString();

        if ($fecha < $inicio) {
            throw ValidationException::withMessages(['fecha_baja' => 'La fecha de baja no puede ser anterior al inicio de la temporada ('.Carbon::parse($inicio)->format('d/m/Y').').']);
        }

        if ($fecha > self::hoy()) {
            throw ValidationException::withMessages(['fecha_baja' => 'La fecha de baja no puede ser posterior a hoy.']);
        }

        $jugador->forceFill(['estado_elegibilidad' => 'Baja', 'motivo_baja' => trim($motivo), 'fecha_baja' => $fecha])->save();
    }

    /** HU-017, criterio 2: la renovación conserva la identidad y crea el vínculo en la nueva temporada. */
    public function renovar(Jugador $jugadorAnterior, Equipo $equipoDestino, User $usuario, int|string|null $dorsal = null): Jugador
    {
        $jugadorId = (int) $jugadorAnterior->getKey();
        $destinoId = (int) $equipoDestino->getKey();

        return $this->auditoria->conMotivo('Renovación de inscripción de jugador', function () use ($jugadorId, $destinoId, $usuario, $dorsal): Jugador {
            [$anterior, $equipos, $usuario] = $this->bloquearContexto($jugadorId, [$destinoId], $usuario);
            $destino = $equipos->get($destinoId);
            if ($destino === null) {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo de destino no existe.']);
            }
            Gate::forUser($usuario)->authorize('renew', $anterior);

            if ($anterior->transferenciasOrigen()->exists()) {
                throw ValidationException::withMessages(['jugador' => 'Esta inscripción se transfirió a otro club; la renovación le corresponde al club actual del jugador.']);
            }

            $anterior->load('equipo.grupo.categoria', 'persona');
            $temporadaAnterior = $this->temporadaDe($anterior);
            $this->exigirInscripcionMasReciente($anterior, $temporadaAnterior, 'Solo se puede renovar desde la inscripción más reciente del jugador.');

            $destino->load('club', 'temporada', 'grupo.categoria');
            $temporada = $destino->temporada;
            if ($temporada === null || $temporada->estado !== 'activa') {
                throw ValidationException::withMessages(['equipo_id' => 'La renovación debe dirigirse a un equipo de la temporada activa.']);
            }
            if ((int) $temporadaAnterior->getKey() === (int) $temporada->getKey()) {
                throw ValidationException::withMessages(['equipo_id' => 'El jugador ya pertenece a la temporada seleccionada.']);
            }
            if ((int) $anterior->equipo->club_id !== (int) $destino->club_id) {
                throw ValidationException::withMessages(['equipo_id' => 'La renovación debe conservar el club de origen del jugador.']);
            }
            if (Jugador::query()->where('persona_id', $anterior->persona_id)->whereHas('equipo', fn (Builder $query): Builder => $query->where('temporada_id', $temporada->getKey()))->exists()) {
                throw ValidationException::withMessages(['jugador' => 'El jugador ya cuenta con una inscripción en la temporada activa.']);
            }

            $origenCategoria = $anterior->equipo->grupo?->categoria;
            $destinoCategoria = $destino->grupo?->categoria;
            if ($origenCategoria === null || $destinoCategoria === null) {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo de origen o destino no tiene una categoría válida.']);
            }
            $this->reglaSaltoCategoria->validar($origenCategoria, $destinoCategoria);
            $this->reglaEdad->validar($anterior->persona, $destinoCategoria, $temporada);

            $numero = $dorsal ?? $anterior->dorsal;
            $this->exigirDorsalLibre($destino, $numero);

            return $destino->jugadores()->create(['persona_id' => $anterior->persona_id, 'dorsal' => $numero, 'estado_elegibilidad' => 'Activo']);
        });
    }

    /**
     * HU-017, criterio 3: transferencia a otro club con las reglas de la temporada vigente.
     *
     * Si el origen es de la temporada activa, pasa a Baja con el motivo de la transferencia.
     * Si es de una temporada cerrada, no se modifica (HU-005, criterio 3): solo se crea la
     * inscripción destino y la transferencia queda en el historial.
     */
    public function transferir(Jugador $jugadorOrigen, int $equipoDestinoId, User $usuario, int|string|null $dorsal = null): HistorialTransferenciaJugador
    {
        $jugadorId = (int) $jugadorOrigen->getKey();

        return $this->auditoria->conMotivo('Transferencia de jugador entre clubes', function () use ($jugadorId, $equipoDestinoId, $usuario, $dorsal): HistorialTransferenciaJugador {
            [$origen, $equipos, $usuario] = $this->bloquearContexto($jugadorId, [$equipoDestinoId], $usuario);
            $equipoOrigen = $equipos->get((int) $origen->equipo_id);
            $destino = $equipos->get($equipoDestinoId);
            if ($equipoOrigen === null || $destino === null) {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo de destino no existe.']);
            }
            Gate::forUser($usuario)->authorize('transfer', $origen);
            if ($origen->estado_elegibilidad !== 'Activo') {
                throw ValidationException::withMessages(['jugador' => 'Solo se puede transferir una inscripción activa.']);
            }
            if ($origen->transferenciasOrigen()->exists()) {
                throw ValidationException::withMessages(['jugador' => 'Esta inscripción ya fue transferida a otro club.']);
            }

            $origen->load('persona');
            $equipoOrigen->load('grupo.categoria');
            $temporadaOrigen = $this->temporadaDe($origen);
            $origenEnTemporadaActiva = $temporadaOrigen->estado === 'activa';
            if (! $origenEnTemporadaActiva) {
                $this->exigirInscripcionMasReciente($origen, $temporadaOrigen, 'Solo se puede transferir desde la inscripción más reciente del jugador.');
            }

            $destino->load('club', 'grupo.categoria', 'temporada');
            $categoriaOrigen = $equipoOrigen->grupo?->categoria;
            $categoriaDestino = $destino->grupo?->categoria;
            $temporada = $destino->temporada;
            $grupo = $destino->grupo;
            if ($temporada === null || $grupo === null || $categoriaDestino === null || (int) $destino->temporada_id !== (int) $grupo->temporada_id || (int) $grupo->temporada_id !== (int) $categoriaDestino->temporada_id) {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo, grupo, categoría y temporada de destino no son consistentes.']);
            }
            if ($temporada->estado !== 'activa') {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo de destino debe pertenecer a la temporada activa.']);
            }
            if ((int) $equipoOrigen->club_id === (int) $destino->club_id) {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo de destino debe pertenecer a otro club.']);
            }
            if ($categoriaOrigen === null) {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo de origen no tiene una categoría válida.']);
            }
            // Las mismas reglas de la temporada vigente que el registro (HU-012 y edad de la categoría).
            $this->reglaSaltoCategoria->validar($categoriaOrigen, $categoriaDestino);
            $this->reglaEdad->validar($origen->persona, $categoriaDestino, $temporada);

            $queryPersona = Jugador::query()->where('persona_id', $origen->persona_id)->whereKeyNot($origen->getKey())->whereHas('equipo', fn (Builder $query): Builder => $query->where('temporada_id', $temporada->getKey()));
            if ((clone $queryPersona)->where('estado_elegibilidad', 'Activo')->exists()) {
                throw ValidationException::withMessages(['jugador' => 'El jugador ya tiene una inscripción activa en la temporada de destino.']);
            }
            // Una baja originada por una transferencia anterior no bloquea: el jugador sigue activo en su nuevo club.
            if ((clone $queryPersona)->whereIn('estado_elegibilidad', ['Suspendido', 'Inelegible', 'Baja'])->whereDoesntHave('transferenciasOrigen')->exists()) {
                throw ValidationException::withMessages(['jugador' => 'El jugador tiene una inscripción suspendida, inelegible o dada de baja en otro equipo de la temporada de destino.']);
            }

            $numero = $dorsal ?? $origen->dorsal;
            $this->exigirDorsalLibre($destino, $numero);

            $motivoBaja = 'Transferencia a '.$destino->club->nombre;
            $fechaBaja = self::hoy();
            if ($origenEnTemporadaActiva) {
                // HU-018 define 4 estados: el origen pasa a Baja y el motivo identifica la transferencia.
                $origen->forceFill(['estado_elegibilidad' => 'Baja', 'motivo_baja' => $motivoBaja, 'fecha_baja' => $fechaBaja])->save();
            }

            $jugadorDestino = $destino->jugadores()->create(['persona_id' => $origen->persona_id, 'dorsal' => $numero, 'estado_elegibilidad' => 'Activo']);
            $historial = HistorialTransferenciaJugador::query()->create([
                'persona_id' => $origen->persona_id,
                'jugador_origen_id' => $origen->getKey(),
                'jugador_destino_id' => $jugadorDestino->getKey(),
                'club_origen_id' => $equipoOrigen->club_id,
                'club_destino_id' => $destino->club_id,
                'equipo_origen_id' => $equipoOrigen->getKey(),
                'equipo_destino_id' => $destino->getKey(),
                'temporada_id' => $temporada->getKey(),
                'transferido_por_usuario_id' => $usuario->getKey(),
            ]);

            // El historial de transferencias no es Auditable: esta es su única entrada en la auditoría.
            $this->auditoria->registrar('jugador_transferido', $historial, [
                'persona_id' => $origen->persona_id,
                'jugador_origen_id' => $origen->getKey(),
                'equipo_origen_id' => $equipoOrigen->getKey(),
                'estado_origen' => 'Activo',
            ], [
                'persona_id' => $origen->persona_id,
                'jugador_origen_id' => $origen->getKey(),
                'jugador_destino_id' => $jugadorDestino->getKey(),
                'club_origen_id' => $equipoOrigen->club_id,
                'club_destino_id' => $destino->club_id,
                'equipo_origen_id' => $equipoOrigen->getKey(),
                'equipo_destino_id' => $destino->getKey(),
                'temporada_id' => $temporada->getKey(),
                'usuario_ejecutor_id' => $usuario->getKey(),
                'origen_modificado' => $origenEnTemporadaActiva,
                'estado_origen' => $origenEnTemporadaActiva ? 'Baja' : 'Activo',
            ]);

            return $historial;
        });
    }

    /**
     * Bloquea club, equipos, usuario, persona e inscripción, en ese orden.
     * HU-018 lo reutiliza para cambiar estados sin interbloquearse con estas operaciones.
     *
     * @param list<int> $equipoDestinoIds
     * @return array{Jugador, Collection<int, Equipo>, User}
     */
    public function bloquearContexto(int $jugadorId, array $equipoDestinoIds, User $usuario): array
    {
        $snapshot = Jugador::query()->findOrFail($jugadorId);
        $equipoOrigenId = (int) $snapshot->equipo_id;
        $personaId = (int) $snapshot->persona_id;
        $ids = array_values(array_unique([$equipoOrigenId, ...$equipoDestinoIds]));
        sort($ids);
        $previos = Equipo::query()->whereKey($ids)->get()->keyBy('id');
        if ($previos->count() !== count($ids)) {
            throw ValidationException::withMessages(['equipo_id' => 'Uno de los equipos de la operación ya no está disponible.']);
        }
        $clubIds = $previos->pluck('club_id')->map(static fn ($id): int => (int) $id)->unique()->sort()->values();
        $clubes = Club::query()->whereKey($clubIds)->orderBy('id')->lockForUpdate()->get();
        if ($clubes->count() !== $clubIds->count()) {
            throw ValidationException::withMessages(['equipo_id' => 'Uno de los clubes de la operación ya no está disponible.']);
        }
        $equipos = Equipo::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($ids as $id) {
            if ((int) $equipos->get($id)?->club_id !== (int) $previos->get($id)?->club_id) {
                throw ValidationException::withMessages(['equipo_id' => 'Un equipo cambió de club mientras se procesaba la operación.']);
            }
        }
        $usuarioBloqueado = User::query()->whereKey($usuario->getKey())->lockForUpdate()->firstOrFail();
        Persona::query()->whereKey($personaId)->lockForUpdate()->firstOrFail();
        $jugadorBloqueado = Jugador::query()->whereKey($jugadorId)->lockForUpdate()->firstOrFail();
        if ((int) $jugadorBloqueado->equipo_id !== $equipoOrigenId || (int) $jugadorBloqueado->persona_id !== $personaId) {
            throw ValidationException::withMessages(['jugador' => 'La inscripción cambió mientras se procesaba la operación.']);
        }
        $jugadorBloqueado->setRelation('equipo', $equipos->get($equipoOrigenId));

        return [$jugadorBloqueado, $equipos, $usuarioBloqueado];
    }

    private function temporadaDe(Jugador $jugador): Temporada
    {
        $temporada = Temporada::query()->find($jugador->equipo->temporada_id);

        if ($temporada === null) {
            throw ValidationException::withMessages(['jugador' => 'La inscripción no tiene una temporada válida.']);
        }

        return $temporada;
    }

    /** Evita operar sobre una inscripción vieja cuando la persona ya tiene una más reciente. */
    private function exigirInscripcionMasReciente(Jugador $jugador, Temporada $temporada, string $mensaje): void
    {
        $hayPosterior = Jugador::query()
            ->where('persona_id', $jugador->persona_id)
            ->whereKeyNot($jugador->getKey())
            ->whereHas('equipo.temporada', fn (Builder $query): Builder => $query->where('fecha_inicio', '>', Carbon::parse($temporada->fecha_inicio)->toDateString()))
            ->exists();

        if ($hayPosterior) {
            throw ValidationException::withMessages(['jugador' => $mensaje]);
        }
    }

    /** El dorsal de una inscripción dada de baja queda libre. */
    private function exigirDorsalLibre(Equipo $equipo, int|string|null $numero): void
    {
        if ($numero === null) {
            return;
        }

        $ocupado = Jugador::query()
            ->where('equipo_id', $equipo->getKey())
            ->where('dorsal', $numero)
            ->where('estado_elegibilidad', '!=', 'Baja')
            ->exists();

        if ($ocupado) {
            throw ValidationException::withMessages(['dorsal' => 'El dorsal '.$numero.' ya está asignado a otro jugador de este equipo.']);
        }
    }
}
