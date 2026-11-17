<?php

namespace App\Services;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/**
 * Captura de una cédula física, en papel, por el Capturista (HU-037, RF-08.1).
 *
 * Todo pasa por CedulaService, así que aplica las mismas validaciones que la captura desde el celular
 * (HU-036): jugadores de la alineación, un capitán por equipo, marcador de 0 a 99, minuto de 0 a 150,
 * autogol solo en un gol y equipo del partido. Se guarda todo o nada: con un dato inválido no queda
 * nada a medias y el error dice en qué fila está. La clave de envío del formulario genera la clave
 * de cada cambio, así que reenviar el mismo formulario no duplica goles, tarjetas ni incidencias (RNF-21).
 */
class CapturaCedulaFisicaService
{
    // Claves de error que no son de una fila del formulario
    private const ERRORES_GENERALES = ['cedula', 'estado', 'clave_envio'];

    public function __construct(
        private readonly CedulaService $cedulas,
        private readonly ValidacionCedulaService $validacion,
        private readonly AuditService $auditoria,
    ) {
    }

    /**
     * Partidos de hoy o anteriores (hora de México) cuya cédula falta o sigue abierta: lo que puede llegar en papel.
     *
     * @return Collection<int, Partido>
     */
    public function pendientes(): Collection
    {
        return Partido::query()
            ->whereIn('estado', CedulaArbitral::ESTADOS_PARTIDO_CAPTURA)
            ->whereNotNull('fecha_partido')
            ->where('fecha_partido', '<=', GestionJugadorService::hoy())
            ->where(fn (Builder $consulta) => $consulta
                ->whereDoesntHave('cedula')
                ->orWhereHas('cedula', fn (Builder $cedula) => $cedula->where('estado', CedulaArbitral::ABIERTA)))
            ->with([
                'local:id,nombre',
                'visitante:id,nombre',
                'cancha:id,nombre',
                'cedula:id,partido_id,estado,origen',
                'jornada:id,grupo_id,numero_jornada',
                'jornada.grupo:id,categoria_id,nombre',
                'jornada.grupo.categoria:id,nombre',
            ])
            ->orderByDesc('fecha_partido')
            ->orderBy('hora_partido')
            ->limit(100)
            ->get();
    }

    /**
     * Abre la cédula si hace falta y guarda en una sola operación lo transcrito del papel. Devuelve los
     * hallazgos de ValidacionCedulaService (HU-075) para revisarlos antes de cerrar; no bloquean el guardado.
     *
     * @param  array{
     *     goles_local: int,
     *     goles_visitante: int,
     *     alineacion: array<int, array{asistio: bool, numero_camiseta: int|null}>,
     *     capitanes: array<int, int|null>,
     *     eventos: array<int, array{jugador_id: int, tipo: string, minuto: int|null, autogol: bool}>,
     *     incidencias: array<int, array{descripcion: string, minuto: int|null, equipo_id: int|null}>,
     *     informe_arbitral: string|null
     * }  $datos
     * @return array{cedula: CedulaArbitral, hallazgos: list<array{tipo: string, mensaje: string}>}
     */
    public function guardar(Partido $partido, array $datos, string $clave, User $usuario): array
    {
        $cedula = $this->auditoria->conMotivo('Captura de la cédula en papel del partido '.$partido->id, fn (): CedulaArbitral => DB::transaction(function () use ($partido, $datos, $clave, $usuario): CedulaArbitral {
            // Abre la cédula, o la pone al día con la plantilla si ya estaba abierta (valida el estado del partido)
            $cedula = $this->cedulas->abrir($partido);
            $cedula->update([
                'origen' => CedulaArbitral::ORIGEN_PAPEL,
                'capturada_por_id' => $usuario->getKey(),
                'capturada_en' => now(),
            ]);

            $this->guardarAlineacion($cedula, $datos['alineacion'], $datos['capitanes'], $clave);

            $this->enFila('marcador', 'Marcador', fn () => $this->cedulas->guardarMarcador(
                $cedula,
                $datos['goles_local'],
                $datos['goles_visitante'],
                $this->claveDe($clave, 'marcador')
            ));

            foreach ($datos['eventos'] as $fila => $evento) {
                $this->enFila('eventos.'.$fila, 'Evento '.($fila + 1), fn () => $this->cedulas->registrarEvento($cedula, $evento, $this->claveDe($clave, 'evento-'.$fila)));
            }

            foreach ($datos['incidencias'] as $fila => $incidencia) {
                $this->enFila('incidencias.'.$fila, 'Incidencia '.($fila + 1), fn () => $this->cedulas->registrarIncidencia($cedula, $incidencia, $this->claveDe($clave, 'incidencia-'.$fila)));
            }

            if ($datos['informe_arbitral'] !== null) {
                $this->enFila('informe_arbitral', 'Reporte arbitral', fn () => $this->cedulas->guardarInforme($cedula, $datos['informe_arbitral'], $this->claveDe($clave, 'informe')));
            }

            return $cedula;
        }));

        return ['cedula' => $cedula->refresh(), 'hallazgos' => $this->validacion->validar($cedula)];
    }

    /**
     * @param  array<int, array{asistio: bool, numero_camiseta: int|null}>  $alineacion
     * @param  array<int, int|null>  $capitanes  capitán elegido por equipo
     */
    private function guardarAlineacion(CedulaArbitral $cedula, array $alineacion, array $capitanes, string $clave): void
    {
        $filas = $cedula->alineaciones()->whereNotNull('jugador_id')->with('jugador.persona:id,nombre,apellidos')->get();
        $enLaCedula = $filas->pluck('jugador_id')->map(fn ($id): int => (int) $id)->all();

        // Un jugador que no es de los equipos del partido no se acepta (RNF-29)
        foreach (array_keys($alineacion) as $jugadorId) {
            if (! in_array((int) $jugadorId, $enLaCedula, true)) {
                throw ValidationException::withMessages(['alineacion.'.$jugadorId => 'El jugador no pertenece a los equipos de este partido.']);
            }
        }

        foreach ($capitanes as $equipoId => $jugadorId) {
            if ($jugadorId !== null && ! $filas->contains(fn ($fila): bool => (int) $fila->jugador_id === $jugadorId && (int) $fila->equipo_id === (int) $equipoId)) {
                throw ValidationException::withMessages(['capitanes.'.$equipoId => 'El capitán debe ser un jugador de su equipo.']);
            }
        }

        foreach ($filas as $fila) {
            $jugadorId = (int) $fila->jugador_id;
            $datos = $alineacion[$jugadorId] ?? ['asistio' => false, 'numero_camiseta' => $fila->numero_camiseta];
            $esCapitan = ($capitanes[(int) $fila->equipo_id] ?? null) === $jugadorId;
            $nombre = trim(($fila->jugador?->persona->nombre ?? '').' '.($fila->jugador?->persona->apellidos ?? ''));

            $this->enFila('alineacion.'.$jugadorId, $nombre !== '' ? $nombre : 'Jugador '.$jugadorId, fn () => $this->cedulas->guardarAlineacion($cedula, $jugadorId, [
                'asistio' => $datos['asistio'] || $esCapitan,
                'numero_camiseta' => $datos['numero_camiseta'],
                'es_capitan' => $esCapitan,
            ], $this->claveDe($clave, 'alineacion-'.$jugadorId)));
        }
    }

    // Clave estable de cada cambio, derivada de la clave del formulario (UUID versión 5)
    private function claveDe(string $clave, string $parte): string
    {
        return Uuid::uuid5($clave, $parte)->toString();
    }

    /**
     * Ejecuta un cambio y, si se rechaza, señala la fila del formulario y la nombra en el mensaje.
     *
     * @template TResultado
     *
     * @param  Closure(): TResultado  $operacion
     * @return TResultado
     */
    private function enFila(string $prefijo, string $etiqueta, Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (ValidationException $error) {
            $errores = [];

            foreach ($error->errors() as $campo => $mensajes) {
                $clave = match (true) {
                    in_array($campo, self::ERRORES_GENERALES, true), $campo === $prefijo => $campo,
                    default => $prefijo.'.'.$campo,
                };
                $errores[$clave] = array_map(
                    fn (string $mensaje): string => in_array($campo, self::ERRORES_GENERALES, true) ? $mensaje : $etiqueta.': '.$mensaje,
                    $mensajes
                );
            }

            throw ValidationException::withMessages($errores);
        }
    }
}
