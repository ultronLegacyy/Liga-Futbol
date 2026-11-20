<?php

namespace App\Services;

use App\Models\AudienciaDisciplinaria;
use App\Models\CatalogoArticulo;
use App\Models\Equipo;
use App\Models\ExpedienteDisciplinario;
use App\Models\Persona;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        if (! $expediente->estaAbierto()) {
            throw ValidationException::withMessages([
                'expediente' => 'No se pueden registrar audiencias en un expediente que ya se encuentra resuelto o archivado.',
            ]);
        }

        $motivoAudit = $motivo ?? 'Registro formal de audiencia disciplinaria para expediente '.$expediente->folio;

        return $this->auditService->conMotivo($motivoAudit, function () use ($expediente, $datos, $usuario): AudienciaDisciplinaria {
            return DB::transaction(function () use ($expediente, $datos, $usuario): AudienciaDisciplinaria {
                $audiencia = AudienciaDisciplinaria::create([
                    'expediente_id' => $expediente->id,
                    'fecha_audiencia' => Carbon::parse($datos['fecha_audiencia']),
                    'lugar_o_medio' => $datos['lugar_o_medio'] ?? 'Sede Oficial de la Liga',
                    'comparecientes' => $datos['comparecientes'],
                    'alegatos_defensa' => $datos['alegatos_defensa'],
                    'resolucion' => $datos['resolucion'] ?? null,
                    'registrado_por_usuario_id' => $usuario->id,
                ]);

                // Si se incluye resolución expresa en la audiencia, persistirla en observaciones del expediente
                if (! empty($datos['resolucion'])) {
                    $expediente->observaciones_comision = $datos['resolucion'];
                    $expediente->save();
                }

                return $audiencia;
            });
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
     *     partidos_suspension?: int|string|null,
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
        if (! $expediente->estaAbierto()) {
            throw ValidationException::withMessages([
                'expediente' => 'No se pueden dictaminar sanciones en un expediente que ya se encuentra resuelto o archivado.',
            ]);
        }

        $personaId = ! empty($datos['persona_id']) ? (int) $datos['persona_id'] : null;
        $equipoId = ! empty($datos['equipo_id']) ? (int) $datos['equipo_id'] : null;

        if ($personaId === null && $equipoId === null) {
            throw ValidationException::withMessages([
                'persona_id' => 'Debe especificar al menos una persona o un equipo sancionado.',
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

        $articuloId = ! empty($datos['articulo_id']) ? (int) $datos['articulo_id'] : $expediente->articulo_id;
        if ($articuloId === null) {
            $primerArticulo = $expediente->articulos()->first();
            $articuloId = $primerArticulo?->id;
        }

        $tipoSancion = $datos['tipo_sancion'] ?? Sancion::TIPO_PARTIDOS;
        $partidosSuspendidos = in_array($tipoSancion, [Sancion::TIPO_PARTIDOS, Sancion::TIPO_EQUIPO], true)
            ? (int) ($datos['partidos_suspendidos'] ?? ($datos['partidos_suspension'] ?? 0))
            : 0;

        if (in_array($tipoSancion, [Sancion::TIPO_PARTIDOS, Sancion::TIPO_EQUIPO], true) && $partidosSuspendidos <= 0) {
            throw ValidationException::withMessages([
                'partidos_suspendidos' => 'Una sanción por partidos debe tener al menos 1 partido de suspensión.',
            ]);
        }

        if ($tipoSancion === Sancion::TIPO_MULTA && (empty($datos['monto_multa']) || (float) $datos['monto_multa'] <= 0)) {
            throw ValidationException::withMessages([
                'monto_multa' => 'Una multa económica debe especificar un monto mayor a cero.',
            ]);
        }

        $motivoAudit = $motivo ?? 'Dictamen de sanción disciplinaria para expediente '.$expediente->folio;

        return $this->auditService->conMotivo($motivoAudit, function () use ($expediente, $datos, $personaId, $equipoId, $articuloId, $tipoSancion, $partidosSuspendidos, $usuario): Sancion {
            return DB::transaction(function () use ($expediente, $datos, $personaId, $equipoId, $articuloId, $tipoSancion, $partidosSuspendidos, $usuario): Sancion {
                $validarInmediatamente = (bool) ($datos['validar_inmediatamente'] ?? false);
                $publicarEnBoletin = (bool) ($datos['publicar_en_boletin'] ?? false);

                $sancion = new Sancion([
                    'expediente_id' => $expediente->id,
                    'persona_id' => $personaId,
                    'equipo_id' => $equipoId,
                    'articulo_id' => $articuloId,
                    'tipo_sancion' => $tipoSancion,
                    'descripcion_sancion' => $datos['descripcion_sancion'] ?? $datos['motivo_resolucion'] ?? 'Sanción disciplinaria dictaminada',
                    'motivo_resolucion' => $datos['motivo_resolucion'] ?? $datos['descripcion_sancion'] ?? null,
                    'partidos_suspendidos' => $partidosSuspendidos,
                    'partidos_cumplidos' => 0,
                    'fecha_inicio' => ! empty($datos['fecha_inicio']) ? Carbon::parse($datos['fecha_inicio']) : Carbon::today(),
                    'fecha_fin' => ! empty($datos['fecha_fin']) ? Carbon::parse($datos['fecha_fin']) : null,
                    'monto_multa' => ! empty($datos['monto_multa']) ? (float) $datos['monto_multa'] : null,
                    'estado' => Sancion::ESTADO_VIGENTE,
                    'publicada_en_boletin' => false,
                ]);

                if ($validarInmediatamente) {
                    $sancion->validado_por_usuario_id = $usuario->id;
                    $sancion->fecha_validacion = now();
                }

                if ($publicarEnBoletin && $validarInmediatamente) {
                    $sancion->publicada_en_boletin = true;
                    $sancion->autorizado_por_usuario_id = $usuario->id;
                    $sancion->autorizado_por_cargo = $usuario->getRoleNames()->first() ?? 'Comisión Disciplinaria';
                    $sancion->fecha_publicacion = now();
                }

                $sancion->save();

                // HU-046: Calcular vigencia automática
                $this->vigenciaService->calcularVigencia($sancion);

                // HU-045: Marcar expediente como resuelto si se indica
                $cerrarExpediente = (bool) ($datos['cerrar_expediente'] ?? true);
                if ($cerrarExpediente) {
                    $expediente->estado = ExpedienteDisciplinario::ESTADO_RESUELTO;
                    $expediente->observaciones_comision = $datos['motivo_resolucion'] ?? $datos['descripcion_sancion'];
                    $expediente->save();
                }

                return $sancion;
            });
        });
    }

    /**
     * Consulta el historial completo, trazable e inmutable de la resolución (HU-045 Criterio 2).
     *
     * @return array{
     *     expediente: ExpedienteDisciplinario,
     *     audiencias: \Illuminate\Database\Eloquent\Collection<int, AudienciaDisciplinaria>,
     *     sanciones: \Illuminate\Database\Eloquent\Collection<int, Sancion>,
     *     articulos: \Illuminate\Database\Eloquent\Collection<int, CatalogoArticulo>,
     *     involucrados: \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpedienteInvolucrado>,
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
