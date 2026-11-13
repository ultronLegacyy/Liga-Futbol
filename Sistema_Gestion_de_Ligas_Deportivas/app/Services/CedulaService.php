<?php

namespace App\Services;

use App\Models\AlineacionPartido;
use App\Models\CedulaArbitral;
use App\Models\EnvioCedula;
use App\Models\EventoPartido;
use App\Models\IncidenciaPartido;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cédula arbitral de un partido (HU-036, RF-07.1). La autorización le toca a quien lo llame.
 * La reutilizan la captura desde papel (HU-037), las firmas y el cierre (HU-075) y HU-040, HU-041 y HU-044.
 *
 * Cada cambio lleva una clave de envío (UUID): un reenvío con la misma clave no duplica nada y devuelve
 * lo que ya se guardó (RNF-21). El trait Auditable deja una sola entrada por operación, con el motivo de conMotivo.
 */
class CedulaService
{
    // Minuto máximo admitido: tiempo regular, alargue y tiempo extra
    private const MINUTO_MAXIMO = 150;

    private const CLAVE_DE_OTRA_CEDULA = 'La clave de envío ya se usó en otra cédula.';

    public function __construct(private readonly AuditService $auditoria)
    {
    }

    /**
     * Partidos asignados al árbitro de la persona del usuario (HU-036): los próximos primero.
     *
     * @return Collection<int, Partido>
     */
    public function partidosAsignados(User $usuario): Collection
    {
        if ($usuario->persona_id === null) {
            return new Collection();
        }

        return Partido::query()
            ->whereHas('arbitro', fn (Builder $arbitro) => $arbitro->where('persona_id', $usuario->persona_id))
            ->where('estado', '!=', 'borrador')
            ->with(['local:id,nombre', 'visitante:id,nombre', 'cancha:id,nombre', 'cedula:id,partido_id,estado'])
            ->orderByRaw('fecha_partido is null')
            ->orderBy('fecha_partido')
            ->orderBy('hora_partido')
            ->get();
    }

    // La cédula abierta del partido, para capturar en ella; si no existe o ya se cerró, se rechaza
    public function abierta(Partido $partido): CedulaArbitral
    {
        $cedula = $partido->cedula()->first();

        if ($cedula === null) {
            throw ValidationException::withMessages(['cedula' => 'El árbitro todavía no abre la cédula de este partido.']);
        }

        if ($cedula->estaCerrada()) {
            $this->rechazarCerrada();
        }

        return $cedula;
    }

    /**
     * Plantilla vigente de los dos equipos, sin las bajas. Los suspendidos aparecen para que HU-075 los detecte.
     *
     * @return Collection<int, Jugador>
     */
    public function plantilla(Partido $partido): Collection
    {
        return $this->consultaPlantilla($partido)->with('persona:id,nombre,apellidos')->get();
    }

    // Jugadores que entraron a la plantilla después de abrir la cédula y todavía no están en la alineación
    public function jugadoresNuevos(CedulaArbitral $cedula, Partido $partido): int
    {
        return $this->consultaPlantilla($partido)
            ->whereNotIn('id', $cedula->alineaciones()->whereNotNull('jugador_id')->select('jugador_id'))
            ->count();
    }

    /**
     * Abre la cédula del partido con la plantilla de los dos equipos. Si ya está abierta, solo suma a los
     * jugadores agregados a la plantilla después, sin tocar lo capturado; abrirla otra vez no duplica nada.
     */
    public function abrir(Partido $partido): CedulaArbitral
    {
        $this->validarEstadoDelPartido($partido);

        $existente = $partido->cedula()->first();

        if ($existente !== null) {
            return DB::transaction(function () use ($existente, $partido): CedulaArbitral {
                $bloqueada = $this->bloquear($existente);
                $this->sumarPlantilla($bloqueada, $partido);

                return $bloqueada;
            });
        }

        return $this->auditoria->conMotivo('Apertura de la cédula arbitral del partido '.$partido->id, function () use ($partido): CedulaArbitral {
            // Dos aperturas simultáneas esperan aquí; la segunda encuentra la cédula ya creada
            $bloqueado = Partido::query()->lockForUpdate()->findOrFail($partido->id);
            $cedula = CedulaArbitral::query()->where('partido_id', $bloqueado->id)->first()
                ?? CedulaArbitral::create(['partido_id' => $bloqueado->id, 'estado' => CedulaArbitral::ABIERTA]);

            if ($cedula->estaCerrada()) {
                $this->rechazarCerrada();
            }

            $this->sumarPlantilla($cedula, $bloqueado);

            return $cedula;
        });
    }

    /**
     * Asistencia, número de camiseta y capitán de un jugador de la alineación. Un capitán por equipo.
     *
     * @param  array{asistio?: bool, numero_camiseta?: int|null, es_capitan?: bool}  $datos
     */
    public function guardarAlineacion(CedulaArbitral $cedula, int $jugadorId, array $datos, string $clave): AlineacionPartido
    {
        $clave = $this->clave($clave);

        return $this->conClaveUnica(fn (): AlineacionPartido => DB::transaction(function () use ($cedula, $jugadorId, $datos, $clave): AlineacionPartido {
            $bloqueada = $this->bloquear($cedula);
            $fila = $bloqueada->alineaciones()->where('jugador_id', $jugadorId)->first();

            if ($fila === null) {
                throw ValidationException::withMessages(['jugador_id' => 'El jugador no está en la alineación de este partido.']);
            }

            // Un reenvío de un cambio ya aplicado no vuelve a cambiar nada, aunque después haya llegado otro más nuevo
            if ($this->yaAplicada($bloqueada, $clave)) {
                return $fila;
            }

            $numero = array_key_exists('numero_camiseta', $datos) ? $datos['numero_camiseta'] : $fila->numero_camiseta;

            if ($numero !== null && ($numero < 0 || $numero > 999)) {
                throw ValidationException::withMessages(['numero_camiseta' => 'El número de camiseta debe estar entre 0 y 999.']);
            }

            $esCapitan = (bool) ($datos['es_capitan'] ?? $fila->es_capitan);

            if ($esCapitan) {
                $bloqueada->alineaciones()
                    ->where('equipo_id', $fila->equipo_id)
                    ->whereKeyNot($fila->id)
                    ->update(['es_capitan' => false]);
            }

            $fila->update([
                'asistio' => (bool) ($datos['asistio'] ?? $fila->asistio),
                'numero_camiseta' => $numero,
                'es_capitan' => $esCapitan,
            ]);

            $bloqueada->envios()->create(['tipo' => EnvioCedula::ALINEACION, 'clave_envio' => $clave]);

            return $fila;
        }));
    }

    // Guardar el mismo marcador otra vez no cambia nada ni deja otra entrada en la auditoría; un reenvío viejo no pisa uno nuevo
    public function guardarMarcador(CedulaArbitral $cedula, int $golesLocal, int $golesVisitante, string $clave): CedulaArbitral
    {
        $clave = $this->clave($clave);

        if ($golesLocal < 0 || $golesVisitante < 0 || $golesLocal > 99 || $golesVisitante > 99) {
            throw ValidationException::withMessages(['marcador' => 'Los goles de cada equipo deben estar entre 0 y 99.']);
        }

        return $this->conClaveUnica(fn (): CedulaArbitral => $this->auditoria->conMotivo('Marcador de la cédula arbitral del partido '.$cedula->partido_id, function () use ($cedula, $golesLocal, $golesVisitante, $clave): CedulaArbitral {
            $bloqueada = $this->bloquear($cedula);

            if ($this->yaAplicada($bloqueada, $clave)) {
                return $bloqueada;
            }

            $bloqueada->update(['goles_local' => $golesLocal, 'goles_visitante' => $golesVisitante]);
            $bloqueada->envios()->create(['tipo' => EnvioCedula::MARCADOR, 'clave_envio' => $clave]);

            return $bloqueada;
        }));
    }

    /**
     * Gol, amarilla o roja de un jugador de la alineación. Un autogol es un Gol que cuenta para el rival.
     *
     * @param  array{jugador_id: int, tipo: string, minuto?: int|null, autogol?: bool}  $datos
     */
    public function registrarEvento(CedulaArbitral $cedula, array $datos, string $clave): EventoPartido
    {
        $clave = $this->clave($clave);

        if (! in_array($datos['tipo'], EventoPartido::TIPOS, true)) {
            throw ValidationException::withMessages(['tipo' => 'El evento debe ser Gol, Amarilla o Roja.']);
        }

        $autogol = (bool) ($datos['autogol'] ?? false);

        if ($autogol && $datos['tipo'] !== 'Gol') {
            throw ValidationException::withMessages(['autogol' => 'Solo un gol puede marcarse como autogol.']);
        }

        $minuto = $this->minuto($datos['minuto'] ?? null);
        $jugadorId = (int) $datos['jugador_id'];

        return $this->conClaveUnica(fn (): EventoPartido => $this->auditoria->conMotivo('Evento '.$datos['tipo'].' en la cédula arbitral del partido '.$cedula->partido_id, function () use ($cedula, $datos, $clave, $autogol, $minuto, $jugadorId): EventoPartido {
            $bloqueada = $this->bloquear($cedula);
            $repetido = EventoPartido::query()->where('clave_envio', $clave)->first();

            if ($repetido !== null) {
                return $this->deLaCedula($repetido, $bloqueada);
            }

            // Evita registrar a un jugador de otro partido cambiando el identificador (RNF-29)
            if (! $bloqueada->alineaciones()->where('jugador_id', $jugadorId)->exists()) {
                throw ValidationException::withMessages(['jugador_id' => 'El jugador no pertenece a los equipos de este partido.']);
            }

            return $bloqueada->eventos()->create([
                'jugador_id' => $jugadorId,
                'tipo' => $datos['tipo'],
                'autogol' => $autogol,
                'minuto' => $minuto,
                'clave_envio' => $clave,
            ]);
        }));
    }

    /**
     * Incidencia en texto libre, con minuto opcional y el equipo al que se refiere, si aplica.
     *
     * @param  array{descripcion: string, minuto?: int|null, equipo_id?: int|string|null}  $datos
     */
    public function registrarIncidencia(CedulaArbitral $cedula, array $datos, string $clave): IncidenciaPartido
    {
        $clave = $this->clave($clave);
        $descripcion = trim($datos['descripcion']);

        if ($descripcion === '' || mb_strlen($descripcion) > 1000) {
            throw ValidationException::withMessages(['descripcion' => 'La descripción de la incidencia es obligatoria y admite hasta 1000 caracteres.']);
        }

        $minuto = $this->minuto($datos['minuto'] ?? null);
        $equipoId = isset($datos['equipo_id']) && $datos['equipo_id'] !== '' ? (int) $datos['equipo_id'] : null;

        return $this->conClaveUnica(fn (): IncidenciaPartido => $this->auditoria->conMotivo('Incidencia en la cédula arbitral del partido '.$cedula->partido_id, function () use ($cedula, $clave, $descripcion, $minuto, $equipoId): IncidenciaPartido {
            $bloqueada = $this->bloquear($cedula);
            $repetida = IncidenciaPartido::query()->where('clave_envio', $clave)->first();

            if ($repetida !== null) {
                return $this->deLaCedula($repetida, $bloqueada);
            }

            $partido = $bloqueada->partido()->firstOrFail();

            if ($equipoId !== null && ! in_array($equipoId, [(int) $partido->equipo_local_id, (int) $partido->equipo_visitante_id], true)) {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo no juega este partido.']);
            }

            return $bloqueada->incidencias()->create([
                'equipo_id' => $equipoId,
                'minuto' => $minuto,
                'descripcion' => $descripcion,
                'clave_envio' => $clave,
            ]);
        }));
    }

    // Solo se abre y se captura mientras el partido está programado, confirmado o en juego
    public function validarEstadoDelPartido(Partido $partido): void
    {
        if (! in_array($partido->estado, CedulaArbitral::ESTADOS_PARTIDO_CAPTURA, true)) {
            throw ValidationException::withMessages([
                'estado' => 'La cédula solo se abre y se captura cuando el partido está programado, confirmado o en juego.',
            ]);
        }
    }

    /**
     * @return Builder<Jugador>
     */
    private function consultaPlantilla(Partido $partido): Builder
    {
        return Jugador::query()
            ->whereIn('equipo_id', [$partido->equipo_local_id, $partido->equipo_visitante_id])
            ->where('estado_elegibilidad', '!=', 'Baja')
            ->orderBy('equipo_id')
            ->orderBy('dorsal');
    }

    // Agrega a la alineación a los jugadores de la plantilla que faltan, con su dorsal como número inicial
    private function sumarPlantilla(CedulaArbitral $cedula, Partido $partido): void
    {
        $nuevos = $this->consultaPlantilla($partido)
            ->whereNotIn('id', $cedula->alineaciones()->whereNotNull('jugador_id')->select('jugador_id'))
            ->get(['id', 'equipo_id', 'dorsal']);

        if ($nuevos->isEmpty()) {
            return;
        }

        $ahora = now();

        AlineacionPartido::query()->insert($nuevos->map(fn (Jugador $jugador): array => [
            'cedula_id' => $cedula->id,
            'jugador_id' => $jugador->id,
            'equipo_id' => $jugador->equipo_id,
            'asistio' => false,
            'numero_camiseta' => $jugador->dorsal,
            'es_capitan' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ])->all());
    }

    // Bloquea la cédula para serializar los cambios; una cédula cerrada, o de un partido fuera de juego, ya no se edita
    private function bloquear(CedulaArbitral $cedula): CedulaArbitral
    {
        $bloqueada = CedulaArbitral::query()->lockForUpdate()->findOrFail($cedula->id);

        if ($bloqueada->estaCerrada()) {
            $this->rechazarCerrada();
        }

        $this->validarEstadoDelPartido($bloqueada->partido()->firstOrFail());

        return $bloqueada;
    }

    private function rechazarCerrada(): never
    {
        throw ValidationException::withMessages(['cedula' => 'La cédula está cerrada: ya no se puede modificar.']);
    }

    // La clave de un cambio de alineación o de marcador ya aplicado; si es de otra cédula, se rechaza
    private function yaAplicada(CedulaArbitral $cedula, string $clave): bool
    {
        $envio = EnvioCedula::query()->where('clave_envio', $clave)->first();

        if ($envio === null) {
            return false;
        }

        if ((int) $envio->cedula_id !== (int) $cedula->id) {
            throw ValidationException::withMessages(['clave_envio' => self::CLAVE_DE_OTRA_CEDULA]);
        }

        return true;
    }

    /**
     * Una clave repetida devuelve lo ya guardado, siempre que sea de la misma cédula.
     *
     * @template TModelo of EventoPartido|IncidenciaPartido
     *
     * @param  TModelo  $registro
     * @return TModelo
     */
    private function deLaCedula(EventoPartido|IncidenciaPartido $registro, CedulaArbitral $cedula): EventoPartido|IncidenciaPartido
    {
        if ((int) $registro->cedula_id !== (int) $cedula->id) {
            throw ValidationException::withMessages(['clave_envio' => self::CLAVE_DE_OTRA_CEDULA]);
        }

        return $registro;
    }

    /**
     * Dos cédulas que guardan la misma clave al mismo tiempo: la segunda choca con el índice único y recibe un 422, no un 500.
     *
     * @template TResultado
     *
     * @param  Closure(): TResultado  $operacion
     * @return TResultado
     */
    private function conClaveUnica(Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['clave_envio' => self::CLAVE_DE_OTRA_CEDULA]);
        }
    }

    private function clave(string $clave): string
    {
        $clave = Str::lower(trim($clave));

        if (! Str::isUuid($clave)) {
            throw ValidationException::withMessages(['clave_envio' => 'La clave de envío no es válida.']);
        }

        return $clave;
    }

    private function minuto(mixed $minuto): ?int
    {
        if ($minuto === null || $minuto === '') {
            return null;
        }

        if (! is_numeric($minuto) || (int) $minuto != $minuto || $minuto < 0 || $minuto > self::MINUTO_MAXIMO) {
            throw ValidationException::withMessages(['minuto' => 'El minuto debe ser un número entero entre 0 y '.self::MINUTO_MAXIMO.'.']);
        }

        return (int) $minuto;
    }
}
