<?php

namespace App\Services;

use App\Models\AlineacionPartido;
use App\Models\CedulaArbitral;
use App\Models\EventoPartido;
use App\Models\IncidenciaPartido;
use App\Models\Jugador;
use App\Models\Partido;
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

    public function __construct(private readonly AuditService $auditoria)
    {
    }

    /**
     * Abre la cédula del partido una sola vez y precarga la alineación con la plantilla de los dos equipos.
     */
    public function abrir(Partido $partido): CedulaArbitral
    {
        $existente = $partido->cedula()->first();

        if ($existente !== null) {
            return $existente;
        }

        return $this->auditoria->conMotivo('Apertura de la cédula arbitral del partido '.$partido->id, function () use ($partido): CedulaArbitral {
            // Dos aperturas simultáneas esperan aquí; la segunda encuentra la cédula ya creada
            $bloqueado = Partido::query()->lockForUpdate()->findOrFail($partido->id);
            $cedula = CedulaArbitral::query()->where('partido_id', $bloqueado->id)->first();

            if ($cedula !== null) {
                return $cedula;
            }

            $cedula = CedulaArbitral::create(['partido_id' => $bloqueado->id, 'estado' => CedulaArbitral::ABIERTA]);

            // Sin las bajas; los suspendidos sí aparecen para que la validación de HU-075 los detecte
            $plantilla = Jugador::query()
                ->whereIn('equipo_id', [$bloqueado->equipo_local_id, $bloqueado->equipo_visitante_id])
                ->where('estado_elegibilidad', '!=', 'Baja')
                ->orderBy('equipo_id')
                ->orderBy('dorsal')
                ->get(['id', 'equipo_id', 'dorsal']);

            $ahora = now();

            AlineacionPartido::query()->insert($plantilla->map(fn (Jugador $jugador): array => [
                'cedula_id' => $cedula->id,
                'jugador_id' => $jugador->id,
                'equipo_id' => $jugador->equipo_id,
                'asistio' => false,
                'numero_camiseta' => $jugador->dorsal,
                'es_capitan' => false,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->all());

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

        return DB::transaction(function () use ($cedula, $jugadorId, $datos, $clave): AlineacionPartido {
            $bloqueada = $this->bloquear($cedula);
            $repetida = AlineacionPartido::query()->where('clave_envio', $clave)->first();

            if ($repetida !== null) {
                return $this->deLaCedula($repetida, $bloqueada);
            }

            $fila = $bloqueada->alineaciones()->where('jugador_id', $jugadorId)->first();

            if ($fila === null) {
                throw ValidationException::withMessages(['jugador_id' => 'El jugador no está en la alineación de este partido.']);
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
                'clave_envio' => $clave,
            ]);

            return $fila;
        });
    }

    // Guardar el mismo marcador otra vez no cambia nada ni deja otra entrada en la auditoría
    public function guardarMarcador(CedulaArbitral $cedula, int $golesLocal, int $golesVisitante): CedulaArbitral
    {
        if ($golesLocal < 0 || $golesVisitante < 0 || $golesLocal > 99 || $golesVisitante > 99) {
            throw ValidationException::withMessages(['marcador' => 'Los goles de cada equipo deben estar entre 0 y 99.']);
        }

        return $this->auditoria->conMotivo('Marcador de la cédula arbitral del partido '.$cedula->partido_id, function () use ($cedula, $golesLocal, $golesVisitante): CedulaArbitral {
            $bloqueada = $this->bloquear($cedula);
            $bloqueada->update(['goles_local' => $golesLocal, 'goles_visitante' => $golesVisitante]);

            return $bloqueada;
        });
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

        return $this->auditoria->conMotivo('Evento '.$datos['tipo'].' en la cédula arbitral del partido '.$cedula->partido_id, function () use ($cedula, $datos, $clave, $autogol, $minuto): EventoPartido {
            $bloqueada = $this->bloquear($cedula);
            $repetido = EventoPartido::query()->where('clave_envio', $clave)->first();

            if ($repetido !== null) {
                return $this->deLaCedula($repetido, $bloqueada);
            }

            // Evita registrar a un jugador de otro partido cambiando el identificador (RNF-29)
            if (! $bloqueada->alineaciones()->where('jugador_id', $datos['jugador_id'])->exists()) {
                throw ValidationException::withMessages(['jugador_id' => 'El jugador no pertenece a los equipos de este partido.']);
            }

            return $bloqueada->eventos()->create([
                'jugador_id' => $datos['jugador_id'],
                'tipo' => $datos['tipo'],
                'autogol' => $autogol,
                'minuto' => $minuto,
                'clave_envio' => $clave,
            ]);
        });
    }

    /**
     * Incidencia en texto libre, con minuto opcional y el equipo al que se refiere, si aplica.
     *
     * @param  array{descripcion: string, minuto?: int|null, equipo_id?: int|null}  $datos
     */
    public function registrarIncidencia(CedulaArbitral $cedula, array $datos, string $clave): IncidenciaPartido
    {
        $clave = $this->clave($clave);
        $descripcion = trim($datos['descripcion']);

        if ($descripcion === '' || mb_strlen($descripcion) > 1000) {
            throw ValidationException::withMessages(['descripcion' => 'La descripción de la incidencia es obligatoria y admite hasta 1000 caracteres.']);
        }

        $minuto = $this->minuto($datos['minuto'] ?? null);

        return $this->auditoria->conMotivo('Incidencia en la cédula arbitral del partido '.$cedula->partido_id, function () use ($cedula, $datos, $clave, $descripcion, $minuto): IncidenciaPartido {
            $bloqueada = $this->bloquear($cedula);
            $repetida = IncidenciaPartido::query()->where('clave_envio', $clave)->first();

            if ($repetida !== null) {
                return $this->deLaCedula($repetida, $bloqueada);
            }

            $equipoId = $datos['equipo_id'] ?? null;
            $partido = $bloqueada->partido()->firstOrFail();

            if ($equipoId !== null && ! in_array($equipoId, [$partido->equipo_local_id, $partido->equipo_visitante_id], true)) {
                throw ValidationException::withMessages(['equipo_id' => 'El equipo no juega este partido.']);
            }

            return $bloqueada->incidencias()->create([
                'equipo_id' => $equipoId,
                'minuto' => $minuto,
                'descripcion' => $descripcion,
                'clave_envio' => $clave,
            ]);
        });
    }

    // Bloquea la cédula para serializar los cambios; una cédula cerrada ya no se edita
    private function bloquear(CedulaArbitral $cedula): CedulaArbitral
    {
        $bloqueada = CedulaArbitral::query()->lockForUpdate()->findOrFail($cedula->id);

        if ($bloqueada->estaCerrada()) {
            throw ValidationException::withMessages(['cedula' => 'La cédula está cerrada: ya no se puede modificar.']);
        }

        return $bloqueada;
    }

    /**
     * Una clave repetida devuelve lo ya guardado, siempre que sea de la misma cédula.
     *
     * @template TModelo of AlineacionPartido|EventoPartido|IncidenciaPartido
     *
     * @param  TModelo  $registro
     * @return TModelo
     */
    private function deLaCedula(AlineacionPartido|EventoPartido|IncidenciaPartido $registro, CedulaArbitral $cedula): AlineacionPartido|EventoPartido|IncidenciaPartido
    {
        if ($registro->cedula_id !== $cedula->id) {
            throw ValidationException::withMessages(['clave_envio' => 'La clave de envío ya se usó en otra cédula.']);
        }

        return $registro;
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
