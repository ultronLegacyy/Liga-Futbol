<?php

namespace App\Services;

use App\Models\CedulaArbitral;
use App\Models\Partido;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * Si ese formulario ya se había guardado, no se vuelve a aplicar ni se informa éxito: hay que abrirlo de nuevo.
 * Respuesta 51 del PO: no se transcribe sobre una cédula que el árbitro ya capturó en campo.
 */
class CapturaCedulaFisicaService
{
    // Claves de error que no son de una fila del formulario
    private const ERRORES_GENERALES = ['cedula', 'estado', 'clave_envio'];

    public const CAPTURADA_EN_CAMPO = 'El árbitro ya capturó esta cédula en campo, así que no se transcribe desde el papel. Las diferencias se corrigen después del cierre.';

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
                ->orWhereHas('cedula', fn (Builder $cedula) => $cedula
                    ->where('estado', CedulaArbitral::ABIERTA)
                    // Respuesta 51: solo las de papel o las que el árbitro abrió sin capturar nada
                    ->where(fn (Builder $origen) => $origen
                        ->where('origen', CedulaArbitral::ORIGEN_PAPEL)
                        ->orWhere(fn (Builder $vacia) => $vacia
                            ->whereNull('firma_capitan_local')
                            ->whereNull('firma_capitan_visitante')
                            ->whereDoesntHave('envios')
                            ->whereDoesntHave('eventos')
                            ->whereDoesntHave('incidencias')))))
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
     * @return array{cedula: CedulaArbitral, hallazgos: list<array{tipo: string, mensaje: string}>, ya_guardado: bool}
     */
    public function guardar(Partido $partido, array $datos, string $clave, User $usuario): array
    {
        $yaGuardado = false;

        // conMotivo ya abre la transacción: la cédula queda bloqueada hasta el final
        $cedula = $this->auditoria->conMotivo('Captura de la cédula en papel del partido '.$partido->id, function () use ($partido, $datos, $clave, $usuario, &$yaGuardado): CedulaArbitral {
            // Abre la cédula, o la pone al día con la plantilla si ya estaba abierta (valida el estado del partido)
            $cedula = $this->cedulas->abrir($partido);

            // Obligatorio 1 (#89): este formulario ya se guardó; con otros datos tampoco se aplica (RNF-21)
            if ($cedula->envios()->where('clave_envio', $this->claveDe($clave, 'marcador'))->exists()) {
                $yaGuardado = true;

                return $cedula;
            }

            if ($cedula->tieneCapturaDeCampo()) {
                throw ValidationException::withMessages(['cedula' => self::CAPTURADA_EN_CAMPO]);
            }

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
        });

        return ['cedula' => $cedula->refresh(), 'hallazgos' => $this->validacion->validar($cedula), 'ya_guardado' => $yaGuardado];
    }

    /**
     * Por qué no se puede transcribir el papel de este partido, o null si sí se puede. Lo usan el formulario,
     * el enlace de la cédula y el guardado, para no ofrecer un formulario que después se rechaza.
     */
    public function motivoNoDisponible(Partido $partido, ?CedulaArbitral $cedula): ?string
    {
        return match (true) {
            $cedula?->estaCerrada() === true => 'La cédula de este partido ya está cerrada: ya no se puede modificar.',
            ! in_array($partido->estado, CedulaArbitral::ESTADOS_PARTIDO_CAPTURA, true) => 'La cédula solo se captura cuando el partido está confirmado o en juego. Estado actual: '.$partido->estado.'.',
            $cedula?->tieneCapturaDeCampo() === true => self::CAPTURADA_EN_CAMPO,
            default => null,
        };
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
            $asistio = $datos['asistio'] || $esCapitan;
            $numero = $datos['numero_camiseta'];

            // Obligatorio 2 (#89): una fila sin cambios no se guarda, así no borra las firmas ni deja otra entrada
            if ((bool) $fila->asistio === $asistio && $this->mismoNumero($fila->numero_camiseta, $numero) && (bool) $fila->es_capitan === $esCapitan) {
                continue;
            }

            $nombre = trim(($fila->jugador?->persona->nombre ?? '').' '.($fila->jugador?->persona->apellidos ?? ''));

            $this->enFila('alineacion.'.$jugadorId, $nombre !== '' ? $nombre : 'Jugador '.$jugadorId, fn () => $this->cedulas->guardarAlineacion($cedula, $jugadorId, [
                'asistio' => $asistio,
                'numero_camiseta' => $numero,
                'es_capitan' => $esCapitan,
            ], $this->claveDe($clave, 'alineacion-'.$jugadorId)));
        }
    }

    private function mismoNumero(mixed $actual, ?int $nuevo): bool
    {
        return ($actual === null ? null : (int) $actual) === $nuevo;
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
