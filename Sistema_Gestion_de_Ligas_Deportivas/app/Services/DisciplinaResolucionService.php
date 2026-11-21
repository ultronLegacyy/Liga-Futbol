<?php

namespace App\Services;

use App\Models\AudienciaDisciplinaria;
use App\Models\CargoFinanciero;
use App\Models\CatalogoArticulo;
use App\Models\Equipo;
use App\Models\ExpedienteDisciplinario;
use App\Models\ExpedienteInvolucrado;
use App\Models\Jugador;
use App\Models\Persona;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Servicio para registro de audiencias, resoluciones y dictamen de sanciones disciplinarias (HU-045 / RF-10.2).
 */
class DisciplinaResolucionService
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly VigenciaSancionService $vigenciaService
    ) {
    }

    /**
     * Registra una audiencia formal para el expediente disciplinario (HU-045 Criterio 1).
     *
     * @param  array{fecha_audiencia: string, comparecientes: string, alegatos_defensa: string, lugar_o_medio?: string|null, resolucion?: string|null}  $datos
     */
    public function registrarAudiencia(
        ExpedienteDisciplinario $expediente,
        array $datos,
        User $usuario,
        ?string $motivo = null
    ): AudienciaDisciplinaria {
        $motivoAudit = $motivo ?? 'Registro formal de audiencia disciplinaria para expediente '.$expediente->folio;

        return $this->auditService->conMotivo($motivoAudit, function () use ($expediente, $datos, $usuario): AudienciaDisciplinaria {
            $bloqueado = ExpedienteDisciplinario::query()
                ->lockForUpdate()
                ->findOrFail($expediente->id);

            if (! $bloqueado->estaAbierto()) {
                throw ValidationException::withMessages([
                    'expediente' => 'No se pueden registrar audiencias en un expediente que ya se encuentra resuelto o archivado.',
                ]);
            }

            $lugar = isset($datos['lugar_o_medio']) && trim((string) $datos['lugar_o_medio']) !== ''
                ? trim((string) $datos['lugar_o_medio'])
                : null;

            if ($lugar === null) {
                throw ValidationException::withMessages([
                    'lugar_o_medio' => 'El lugar o medio de la audiencia es obligatorio.',
                ]);
            }

            $audiencia = AudienciaDisciplinaria::create([
                'expediente_id' => $bloqueado->id,
                'fecha_audiencia' => Carbon::parse($datos['fecha_audiencia']),
                'lugar_o_medio' => $lugar,
                'comparecientes' => $datos['comparecientes'],
                'alegatos_defensa' => $datos['alegatos_defensa'],
                'resolucion' => $datos['resolucion'] ?? null,
                'registrado_por_usuario_id' => $usuario->id,
            ]);

            if (! empty($datos['resolucion'])) {
                $bloqueado->observaciones_comision = $datos['resolucion'];
                $bloqueado->save();
            }

            return $audiencia;
        });
    }

    /**
     * Dictamina y guarda la sanción de un expediente disciplinario (HU-045 Criterio 1).
     *
     * @param  array{
     *     persona_id?: int|string|null,
     *     equipo_id?: int|string|null,
     *     articulo_id?: int|string|null,
     *     tipo_sancion?: string|null,
     *     partidos_suspendidos?: int|string|null,
     *     fecha_inicio?: string|null,
     *     fecha_fin?: string|null,
     *     monto_multa?: float|string|null,
     *     descripcion_sancion?: string|null,
     *     motivo_resolucion?: string|null,
     *     validar_inmediatamente?: bool|null,
     *     publicar_en_boletin?: bool|null,
     *     cerrar_expediente?: bool|null
     * }  $datos
     */
    public function dictaminarSancion(
        ExpedienteDisciplinario $expediente,
        array $datos,
        User $usuario,
        ?string $motivo = null
    ): Sancion {
        $personaId = ! empty($datos['persona_id']) ? (int) $datos['persona_id'] : null;
        $equipoId = ! empty($datos['equipo_id']) ? (int) $datos['equipo_id'] : null;

        if ($personaId === null && $equipoId === null) {
            throw ValidationException::withMessages([
                'persona_id' => 'Debe especificar al menos una persona involucrada o un equipo del expediente.',
            ]);
        }

        if ($personaId !== null && ! Persona::query()->whereKey($personaId)->exists()) {
            throw ValidationException::withMessages([
                'persona_id' => 'La persona seleccionada no existe en el sistema.',
            ]);
        }

        if ($equipoId !== null && ! Equipo::query()->whereKey($equipoId)->exists()) {
            throw ValidationException::withMessages([
                'equipo_id' => 'El equipo seleccionado no existe en el sistema.',
            ]);
        }

        $tipoSancion = (string) ($datos['tipo_sancion'] ?? Sancion::TIPO_PARTIDOS);
        if (! in_array($tipoSancion, Sancion::TIPOS_PERMITIDOS, true)) {
            throw ValidationException::withMessages([
                'tipo_sancion' => 'El tipo de sanción seleccionado no es válido para el reglamento vigente.',
            ]);
        }

        if (in_array($tipoSancion, Sancion::TIPOS_INHABILITANTES, true) && $personaId === null) {
            throw ValidationException::withMessages([
                'persona_id' => 'Las suspensiones deportivas por partidos o por tiempo requieren seleccionar a una persona involucrada en el expediente.',
            ]);
        }

        $partidosSuspendidos = $tipoSancion === Sancion::TIPO_PARTIDOS
            ? (int) ($datos['partidos_suspendidos'] ?? 0)
            : 0;

        if ($tipoSancion === Sancion::TIPO_PARTIDOS && $partidosSuspendidos <= 0) {
            throw ValidationException::withMessages([
                'partidos_suspendidos' => 'Una sanción por partidos debe tener al menos 1 partido de suspensión.',
            ]);
        }

        if ($tipoSancion === Sancion::TIPO_TEMPORAL && empty($datos['fecha_fin'])) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'Una suspensión temporal requiere especificar obligatoriamente la fecha de término.',
            ]);
        }

        if ($tipoSancion === Sancion::TIPO_MULTA && (empty($datos['monto_multa']) || (float) $datos['monto_multa'] <= 0)) {
            throw ValidationException::withMessages([
                'monto_multa' => 'Una multa económica debe especificar un monto mayor a cero.',
            ]);
        }

        $motivoAudit = $motivo ?? 'Dictamen de sanción disciplinaria para expediente '.$expediente->folio;

        return $this->auditService->conMotivo($motivoAudit, function () use ($expediente, $datos, $personaId, $equipoId, $tipoSancion, $partidosSuspendidos, $usuario): Sancion {
            $bloqueado = ExpedienteDisciplinario::query()
                ->with(['partido.jornada.grupo.categoria'])
                ->lockForUpdate()
                ->findOrFail($expediente->id);

            if (! $bloqueado->estaAbierto()) {
                throw ValidationException::withMessages([
                    'expediente' => 'No se pueden dictaminar sanciones en un expediente que ya se encuentra resuelto o archivado.',
                ]);
            }

            if ($personaId !== null) {
                $esInvolucrado = ExpedienteInvolucrado::query()
                    ->where('expediente_id', $bloqueado->id)
                    ->where('persona_id', $personaId)
                    ->exists();

                if (! $esInvolucrado) {
                    throw ValidationException::withMessages([
                        'persona_id' => 'La persona seleccionada no forma parte de los involucrados de este expediente disciplinario.',
                    ]);
                }
            }

            if ($equipoId !== null) {
                $partido = $bloqueado->partido;
                $equipoValido = $partido !== null
                    ? in_array($equipoId, [(int) $partido->equipo_local_id, (int) $partido->equipo_visitante_id], true)
                    : ExpedienteInvolucrado::query()
                        ->where('expediente_id', $bloqueado->id)
                        ->where('equipo_id', $equipoId)
                        ->exists();

                if (! $equipoValido) {
                    throw ValidationException::withMessages([
                        'equipo_id' => 'El equipo seleccionado no corresponde al partido ni a los involucrados de este expediente.',
                    ]);
                }
            }

            $articuloId = ! empty($datos['articulo_id']) ? (int) $datos['articulo_id'] : $bloqueado->articulo_id;
            if ($articuloId === null) {
                $primerArticulo = $bloqueado->articulos()->first();
                $articuloId = $primerArticulo?->id;
            }

            $validarInmediatamente = (bool) ($datos['validar_inmediatamente'] ?? false);
            $publicarEnBoletin = (bool) ($datos['publicar_en_boletin'] ?? false);

            if ($publicarEnBoletin && ! $validarInmediatamente) {
                throw ValidationException::withMessages([
                    'publicar_en_boletin' => 'RNF-44: No se puede publicar una sanción en el boletín sin validarla formalmente.',
                ]);
            }

            $fechaInicio = ! empty($datos['fecha_inicio'])
                ? Carbon::parse($datos['fecha_inicio'], Sancion::ZONA_HORARIA)->toDateString()
                : Carbon::today(Sancion::ZONA_HORARIA)->toDateString();

            $fechaFin = $tipoSancion === Sancion::TIPO_TEMPORAL && ! empty($datos['fecha_fin'])
                ? Carbon::parse($datos['fecha_fin'], Sancion::ZONA_HORARIA)->toDateString()
                : null;

            $sancion = new Sancion([
                'expediente_id' => $bloqueado->id,
                'persona_id' => $personaId,
                'equipo_id' => $equipoId,
                'articulo_id' => $articuloId,
                'tipo_sancion' => $tipoSancion,
                'descripcion_sancion' => $datos['descripcion_sancion'] ?? $datos['motivo_resolucion'] ?? 'Sanción disciplinaria dictaminada',
                'motivo_resolucion' => $datos['motivo_resolucion'] ?? $datos['descripcion_sancion'] ?? null,
                'partidos_suspendidos' => $partidosSuspendidos,
                'partidos_cumplidos' => 0,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'monto_multa' => $tipoSancion === Sancion::TIPO_MULTA && ! empty($datos['monto_multa']) ? (float) $datos['monto_multa'] : null,
                'estado' => Sancion::ESTADO_VIGENTE,
                'publicada_en_boletin' => false,
            ]);

            if ($validarInmediatamente) {
                $sancion->validado_por_usuario_id = $usuario->id;
                $sancion->fecha_validacion = now();
            }

            if ($publicarEnBoletin) {
                $sancion->publicada_en_boletin = true;
                $sancion->autorizado_por_usuario_id = $usuario->id;
                $sancion->autorizado_por_cargo = $usuario->getRoleNames()->first() ?? 'Comisión Disciplinaria';
                $sancion->fecha_publicacion = now();
            }

            $sancion->save();

            $this->vigenciaService->calcularVigencia($sancion);

            if ($tipoSancion === Sancion::TIPO_MULTA) {
                $this->generarCargoMultaClub($bloqueado, $sancion, $equipoId, $personaId, $fechaInicio);
            }

            $cerrarExpediente = (bool) ($datos['cerrar_expediente'] ?? true);
            if ($cerrarExpediente) {
                $bloqueado->estado = ExpedienteDisciplinario::ESTADO_RESUELTO;
                $bloqueado->observaciones_comision = $datos['motivo_resolucion'] ?? $datos['descripcion_sancion'] ?? null;
                $bloqueado->save();
            }

            return $sancion;
        });
    }

    /**
     * Genera el cargo financiero por multa al club correspondiente (PO #47).
     */
    private function generarCargoMultaClub(
        ExpedienteDisciplinario $expediente,
        Sancion $sancion,
        ?int $equipoId,
        ?int $personaId,
        string $fechaVencimiento
    ): void {
        $equipo = $equipoId !== null ? Equipo::find($equipoId) : null;

        if ($equipo === null && $personaId !== null) {
            $involucrado = $expediente->involucrados()
                ->with('equipo')
                ->where('persona_id', $personaId)
                ->whereNotNull('equipo_id')
                ->first();
            $equipo = $involucrado?->equipo;

            if ($equipo === null) {
                $jugador = Jugador::query()
                    ->with('equipo')
                    ->where('persona_id', $personaId)
                    ->where('estado_elegibilidad', '!=', 'Baja')
                    ->latest('id')
                    ->first();
                $equipo = $jugador?->equipo;
            }
        }

        $clubId = $equipo?->club_id;
        if ($clubId === null) {
            return;
        }

        $grupo = $expediente->partido?->jornada?->grupo;
        $temporadaId = $grupo !== null
            ? ($grupo->temporada_id ?? $grupo->categoria->temporada_id)
            : null;

        CargoFinanciero::create([
            'club_id' => $clubId,
            'equipo_id' => $equipo->id,
            'persona_id' => $personaId,
            'temporada_id' => $temporadaId,
            'concepto' => 'Multa',
            'monto' => $sancion->monto_multa,
            'vencimiento' => $fechaVencimiento,
            'estado' => 'Pendiente',
        ]);
    }

    /**
     * Consulta el historial completo, trazable e inmutable de la resolución (HU-045 Criterio 2).
     *
     * @return array{
     *     expediente: ExpedienteDisciplinario,
     *     audiencias: \Illuminate\Database\Eloquent\Collection<int, AudienciaDisciplinaria>,
     *     sanciones: \Illuminate\Database\Eloquent\Collection<int, Sancion>,
     *     articulos: \Illuminate\Database\Eloquent\Collection<int, CatalogoArticulo>,
     *     involucrados: \Illuminate\Database\Eloquent\Collection<int, ExpedienteInvolucrado>,
     *     evidencias: \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpedienteEvidencia>
     * }
     */
    public function obtenerHistorialResolucion(ExpedienteDisciplinario $expediente): array
    {
        $expediente->loadMissing([
            'partido.local:id,nombre',
            'partido.visitante:id,nombre',
            'partido.jornada:id,numero_jornada',
            'articulo',
            'articulos',
            'involucrados.persona:id,nombre,apellidos',
            'involucrados.equipo:id,nombre',
            'evidencias',
            'audiencias.registradoPor:id,name,email',
            'sanciones.persona:id,nombre,apellidos',
            'sanciones.equipo:id,nombre',
            'sanciones.articulo:id,codigo,descripcion',
            'sanciones.partidoHasta.jornada:id,numero_jornada',
            'sanciones.partidoHasta.local:id,nombre',
            'sanciones.partidoHasta.visitante:id,nombre',
            'sanciones.validadoPor:id,name,email',
            'sanciones.autorizadoPor:id,name,email',
        ]);

        return [
            'expediente' => $expediente,
            'audiencias' => $expediente->audiencias,
            'sanciones' => $expediente->sanciones,
            'articulos' => $expediente->articulos,
            'involucrados' => $expediente->involucrados,
            'evidencias' => $expediente->evidencias,
        ];
    }
}
