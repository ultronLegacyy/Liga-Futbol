<?php

namespace App\Services;

use App\Models\CatalogoArticulo;
use App\Models\CedulaArbitral;
use App\Models\ExpedienteArticulo;
use App\Models\ExpedienteDisciplinario;
use App\Models\ExpedienteEvidencia;
use App\Models\ExpedienteInvolucrado;
use App\Models\Partido;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de generación y gestión de expedientes disciplinarios (HU-044 / RF-10.1).
 * Convierte automáticamente incidencias, tarjetas y reportes arbitrales de la cédula
 * en un expediente oficial del Tribunal de Penas / Comisión Disciplinaria.
 */
class ExpedienteDisciplinarioService
{
    public function __construct(
        private readonly AuditService $auditoria,
    ) {
    }

    /**
     * Genera automáticamente un expediente disciplinario a partir de las incidencias de la cédula, si aplica.
     * Criterio 1: Solo genera expediente si la cédula cuenta con incidencias (tarjetas, expulsiones, reportes).
     */
    public function generarDesdeCedulaSiAplica(CedulaArbitral $cedula): ?ExpedienteDisciplinario
    {
        if (! $cedula->tieneIncidenciasDisciplinarias()) {
            return null;
        }

        // Idempotencia: no duplicar expediente si ya existe uno generado para esta cédula
        $existente = ExpedienteDisciplinario::where('cedula_id', $cedula->id)->first();
        if ($existente !== null) {
            return $existente;
        }

        $partido = $cedula->partido()->with(['local:id,nombre', 'visitante:id,nombre', 'jornada'])->firstOrFail();

        return $this->auditoria->conMotivo(
            'Generación automática de expediente disciplinario por cierre de cédula del partido '.$partido->id,
            function () use ($cedula, $partido): ExpedienteDisciplinario {
                return DB::transaction(function () use ($cedula, $partido): ExpedienteDisciplinario {
                    $localNombre = $partido->local->nombre;
                    $visitanteNombre = $partido->visitante->nombre;
                    $numeroJornada = $partido->jornada->numero_jornada;
                    $titulo = "Expediente disciplinario: {$localNombre} vs {$visitanteNombre} (Jornada {$numeroJornada})";

                    // Crear el expediente base con folio inicial null para evitar colisiones concurrentes (Punto 1)
                    $expediente = ExpedienteDisciplinario::create([
                        'folio' => null,
                        'cedula_id' => $cedula->id,
                        'partido_id' => $partido->id,
                        'titulo' => $titulo,
                        'origen' => 'cedula_arbitral',
                        'generado_automaticamente' => true,
                        'fecha_apertura' => now(),
                        'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO,
                    ]);

                    $year = now()->year;
                    $folio = sprintf('EXP-%d-%04d', $year, $expediente->id);
                    $expediente->update(['folio' => $folio]);

                    $resumenHechos = [];

                    // 1. Procesar eventos de tarjetas (Amarillas y Rojas)
                    $eventosTarjetas = $cedula->eventos()
                        ->whereIn('tipo', ['Amarilla', 'Roja'])
                        ->with(['jugador.persona', 'jugador.equipo'])
                        ->orderBy('minuto')
                        ->get();

                    // Conteo progresivo de tarjetas amarillas por jugador para tipificar correctamente la primera amonestación y la subsecuente doble amarilla
                    $amarillasVistasPorJugador = [];

                    foreach ($eventosTarjetas as $evento) {
                        $jugador = $evento->jugador;
                        $persona = $jugador?->persona;
                        $personaId = $persona?->id;
                        $equipoId = $jugador?->equipo_id;
                        $equipoNombre = $jugador?->equipo !== null ? $jugador->equipo->nombre : 'Equipo no identificado';
                        $personaNombre = $persona !== null ? "{$persona->nombre} {$persona->apellidos}" : "Jugador #{$evento->jugador_id}";

                        if ($evento->tipo === 'Roja') {
                            $tipoInvolucrado = 'jugador_expulsado';
                            $descInvolucrado = "Expulsión con tarjeta roja al minuto {$evento->minuto}";
                            $descEvidencia = "Tarjeta Roja directa al jugador {$personaNombre} ({$equipoNombre}) al minuto {$evento->minuto}";
                        } else {
                            $jugadorId = $evento->jugador_id ?? 0;
                            $amarillasVistasPorJugador[$jugadorId] = ($amarillasVistasPorJugador[$jugadorId] ?? 0) + 1;
                            $esSegundaAmarilla = $amarillasVistasPorJugador[$jugadorId] >= 2;

                            if ($esSegundaAmarilla) {
                                $tipoInvolucrado = 'jugador_expulsado';
                                $descInvolucrado = "Expulsión por doble tarjeta amarilla (segunda al minuto {$evento->minuto})";
                                $descEvidencia = "Segunda Tarjeta Amarilla y consecuente expulsión del jugador {$personaNombre} ({$equipoNombre}) al minuto {$evento->minuto}";
                            } else {
                                $tipoInvolucrado = 'jugador_amonestado';
                                $descInvolucrado = "Amonestación con tarjeta amarilla al minuto {$evento->minuto}";
                                $descEvidencia = "Tarjeta Amarilla al jugador {$personaNombre} ({$equipoNombre}) al minuto {$evento->minuto}";
                            }
                        }

                        $resumenHechos[] = $descEvidencia;

                        // Registrar persona involucrada (auditable)
                        ExpedienteInvolucrado::create([
                            'expediente_id' => $expediente->id,
                            'persona_id' => $personaId,
                            'equipo_id' => $equipoId,
                            'tipo_involucrado' => $tipoInvolucrado,
                            'descripcion_participacion' => $descInvolucrado,
                        ]);

                        // Registrar evidencia de tarjeta (auditable)
                        ExpedienteEvidencia::create([
                            'expediente_id' => $expediente->id,
                            'tipo_evidencia' => ExpedienteEvidencia::TIPO_TARJETA,
                            'descripcion' => $descEvidencia,
                            'minuto' => $evento->minuto,
                            'evento_id' => $evento->id,
                        ]);
                    }

                    // 2. Procesar incidencias del partido en texto libre
                    $incidencias = $cedula->incidencias()->with('equipo')->orderBy('minuto')->get();
                    foreach ($incidencias as $incidencia) {
                        $minutoTexto = $incidencia->minuto !== null ? "al minuto {$incidencia->minuto}" : 'durante el partido';
                        $equipoNombre = $incidencia->equipo !== null ? $incidencia->equipo->nombre : 'No especificado';
                        $descEvidencia = "Incidencia reportada {$minutoTexto} ({$equipoNombre}): {$incidencia->descripcion}";
                        $resumenHechos[] = $descEvidencia;

                        if ($incidencia->equipo_id !== null) {
                            ExpedienteInvolucrado::create([
                                'expediente_id' => $expediente->id,
                                'persona_id' => null,
                                'equipo_id' => $incidencia->equipo_id,
                                'tipo_involucrado' => 'equipo_implicado',
                                'descripcion_participacion' => "Incidencia registrada {$minutoTexto}: {$incidencia->descripcion}",
                            ]);
                        }

                        ExpedienteEvidencia::create([
                            'expediente_id' => $expediente->id,
                            'tipo_evidencia' => ExpedienteEvidencia::TIPO_INCIDENCIA,
                            'descripcion' => $descEvidencia,
                            'minuto' => $incidencia->minuto,
                            'incidencia_id' => $incidencia->id,
                        ]);
                    }

                    // 3. Procesar informe arbitral general
                    if (! empty(trim((string) $cedula->informe_arbitral))) {
                        $descInforme = 'Informe Arbitral Oficial: '.$cedula->informe_arbitral;
                        $resumenHechos[] = $descInforme;

                        ExpedienteEvidencia::create([
                            'expediente_id' => $expediente->id,
                            'tipo_evidencia' => ExpedienteEvidencia::TIPO_INFORME,
                            'descripcion' => $descInforme,
                            'minuto' => null,
                        ]);
                    }

                    // Asegurar que los dos clubes participantes estén vinculados como involucrados
                    $equiposRegistrados = $expediente->involucrados()->whereNotNull('equipo_id')->pluck('equipo_id')->all();
                    if (! in_array($partido->equipo_local_id, $equiposRegistrados, true)) {
                        ExpedienteInvolucrado::create([
                            'expediente_id' => $expediente->id,
                            'persona_id' => null,
                            'equipo_id' => $partido->equipo_local_id,
                            'tipo_involucrado' => 'club_local',
                            'descripcion_participacion' => 'Equipo participante en el encuentro como local',
                        ]);
                    }
                    if (! in_array($partido->equipo_visitante_id, $equiposRegistrados, true)) {
                        ExpedienteInvolucrado::create([
                            'expediente_id' => $expediente->id,
                            'persona_id' => null,
                            'equipo_id' => $partido->equipo_visitante_id,
                            'tipo_involucrado' => 'club_visitante',
                            'descripcion_participacion' => 'Equipo participante en el encuentro como visitante',
                        ]);
                    }

                    // Actualizar hechos compilados del expediente sin especular artículos reglamentarios presuntos (Punto 3)
                    $expediente->update([
                        'descripcion_hechos' => implode("\n", $resumenHechos),
                    ]);

                    return $expediente->fresh(['articulos', 'involucrados.persona', 'involucrados.equipo', 'evidencias']);
                });
            }
        );
    }

    /**
     * Consulta detallada de un expediente disciplinario con todas sus relaciones cargadas (HU-044 Criterio 2).
     */
    public function obtenerExpediente(int|ExpedienteDisciplinario $expediente): ExpedienteDisciplinario
    {
        $modelo = is_int($expediente) ? ExpedienteDisciplinario::findOrFail($expediente) : $expediente;

        return $modelo->loadMissing([
            'partido.local',
            'partido.visitante',
            'partido.cancha',
            'partido.jornada.grupo.categoria.temporada',
            'cedula',
            'articulo',
            'articulos',
            'involucrados.persona',
            'involucrados.equipo',
            'evidencias',
        ]);
    }

    /**
     * Asocia manualmente un artículo presunto adicional al expediente (Tribunal de Penas / Comisión Disciplinaria).
     * Registra auditoría explícita a través del modelo pivote ExpedienteArticulo (HU-044 / RNF-30).
     */
    public function asociarArticuloPresunto(ExpedienteDisciplinario $expediente, int $articuloId, ?string $observaciones = null): void
    {
        $articulo = CatalogoArticulo::findOrFail($articuloId);

        $this->auditoria->conMotivo(
            "Imputación de presunta infracción al artículo {$articulo->codigo} en expediente {$expediente->folio}",
            function () use ($expediente, $articuloId, $observaciones): void {
                $pivote = ExpedienteArticulo::where('expediente_id', $expediente->id)
                    ->where('articulo_id', $articuloId)
                    ->first();

                if ($pivote !== null) {
                    $pivote->update([
                        'observaciones' => $observaciones ?? $pivote->observaciones,
                    ]);
                } else {
                    ExpedienteArticulo::create([
                        'expediente_id' => $expediente->id,
                        'articulo_id' => $articuloId,
                        'observaciones' => $observaciones ?? 'Imputado durante la revisión del expediente disciplinario',
                    ]);
                }

                if ($expediente->articulo_id === null) {
                    $expediente->update(['articulo_id' => $articuloId]);
                }
            }
        );
    }

    /**
     * Remueve un artículo presunto del expediente (Tribunal de Penas / Comisión Disciplinaria).
     * Registra auditoría explícita de eliminación a través del modelo pivote ExpedienteArticulo (HU-044 / RNF-30).
     */
    public function desasociarArticuloPresunto(ExpedienteDisciplinario $expediente, int $articuloId): void
    {
        $articulo = CatalogoArticulo::findOrFail($articuloId);

        $this->auditoria->conMotivo(
            "Desestimación de presunta infracción al artículo {$articulo->codigo} en expediente {$expediente->folio}",
            function () use ($expediente, $articuloId): void {
                $pivote = ExpedienteArticulo::where('expediente_id', $expediente->id)
                    ->where('articulo_id', $articuloId)
                    ->first();

                if ($pivote !== null) {
                    $pivote->delete();
                }

                // Si era el artículo principal, reasignar al siguiente artículo disponible o null
                if ($expediente->articulo_id === $articuloId) {
                    $siguiente = $expediente->articulos()->first();
                    $expediente->update(['articulo_id' => $siguiente?->id]);
                }
            }
        );
    }
}
