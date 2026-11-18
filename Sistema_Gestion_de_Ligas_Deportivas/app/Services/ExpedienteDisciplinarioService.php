<?php

namespace App\Services;

use App\Models\CatalogoArticulo;
use App\Models\CedulaArbitral;
use App\Models\ExpedienteDisciplinario;
use App\Models\ExpedienteEvidencia;
use App\Models\ExpedienteInvolucrado;
use App\Models\Partido;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                    $year = now()->year;
                    $consecutivo = (int) (ExpedienteDisciplinario::max('id') ?? 0) + 1;
                    $folio = sprintf('EXP-%d-%04d', $year, $consecutivo);

                    $localNombre = $partido->local->nombre;
                    $visitanteNombre = $partido->visitante->nombre;
                    $numeroJornada = $partido->jornada->numero_jornada;
                    $titulo = "Expediente disciplinario: {$localNombre} vs {$visitanteNombre} (Jornada {$numeroJornada})";

                    // Crear el expediente base
                    $expediente = ExpedienteDisciplinario::create([
                        'folio' => $folio,
                        'cedula_id' => $cedula->id,
                        'partido_id' => $partido->id,
                        'titulo' => $titulo,
                        'origen' => 'cedula_arbitral',
                        'generado_automaticamente' => true,
                        'fecha_apertura' => now(),
                        'estado' => ExpedienteDisciplinario::ESTADO_ABIERTO,
                    ]);

                    $codigosArticulos = [];
                    $resumenHechos = [];

                    // 1. Procesar eventos de tarjetas (Amarillas y Rojas)
                    $eventosTarjetas = $cedula->eventos()
                        ->whereIn('tipo', ['Amarilla', 'Roja'])
                        ->with(['jugador.persona', 'jugador.equipo'])
                        ->orderBy('minuto')
                        ->get();

                    // Conteo de tarjetas por jugador para detectar doble amarilla
                    $conteoAmarillasPorJugador = [];
                    foreach ($eventosTarjetas as $evento) {
                        if ($evento->tipo === 'Amarilla' && $evento->jugador_id !== null) {
                            $conteoAmarillasPorJugador[$evento->jugador_id] = ($conteoAmarillasPorJugador[$evento->jugador_id] ?? 0) + 1;
                        }
                    }

                    foreach ($eventosTarjetas as $evento) {
                        $jugador = $evento->jugador;
                        $persona = $jugador?->persona;
                        $personaId = $persona?->id;
                        $equipoId = $jugador?->equipo_id;
                        $equipoNombre = $jugador?->equipo !== null ? $jugador->equipo->nombre : 'Equipo no identificado';
                        $personaNombre = $persona !== null ? "{$persona->nombre} {$persona->apellidos}" : "Jugador #{$evento->jugador_id}";

                        if ($evento->tipo === 'Roja') {
                            $codigosArticulos[] = 'ART-12'; // Juego brusco grave o conducta violenta
                            $tipoInvolucrado = 'jugador_expulsado';
                            $descInvolucrado = "Expulsión con tarjeta roja al minuto {$evento->minuto}";
                            $descEvidencia = "Tarjeta Roja directa al jugador {$personaNombre} ({$equipoNombre}) al minuto {$evento->minuto}";
                        } else {
                            $esDobleAmarilla = ($conteoAmarillasPorJugador[$evento->jugador_id ?? 0] ?? 0) >= 2;
                            if ($esDobleAmarilla) {
                                $codigosArticulos[] = 'ART-14'; // Expulsión por doble amonestación
                                $tipoInvolucrado = 'jugador_expulsado';
                                $descInvolucrado = "Expulsión por doble tarjeta amarilla (segunda al minuto {$evento->minuto})";
                                $descEvidencia = "Segunda Tarjeta Amarilla y consecuente expulsión del jugador {$personaNombre} ({$equipoNombre}) al minuto {$evento->minuto}";
                            } else {
                                $codigosArticulos[] = 'ART-08'; // Amonestación por conducta antideportiva
                                $tipoInvolucrado = 'jugador_amonestado';
                                $descInvolucrado = "Amonestación con tarjeta amarilla al minuto {$evento->minuto}";
                                $descEvidencia = "Tarjeta Amarilla al jugador {$personaNombre} ({$equipoNombre}) al minuto {$evento->minuto}";
                            }
                        }

                        $resumenHechos[] = $descEvidencia;

                        // Registrar persona involucrada
                        ExpedienteInvolucrado::create([
                            'expediente_id' => $expediente->id,
                            'persona_id' => $personaId,
                            'equipo_id' => $equipoId,
                            'tipo_involucrado' => $tipoInvolucrado,
                            'descripcion_participacion' => $descInvolucrado,
                        ]);

                        // Registrar evidencia de tarjeta
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

                        // Detección heurística de artículos reglamentarios presuntos
                        $textoMin = mb_strtolower($incidencia->descripcion, 'UTF-8');
                        if (str_contains($textoMin, 'insult') || str_contains($textoMin, 'ofens') || str_contains($textoMin, 'arbitro') || str_contains($textoMin, 'árbitro')) {
                            $codigosArticulos[] = 'ART-15';
                        }
                        if (str_contains($textoMin, 'porra') || str_contains($textoMin, 'invas') || str_contains($textoMin, 'riña') || str_contains($textoMin, 'bronca') || str_contains($textoMin, 'pelea')) {
                            $codigosArticulos[] = 'ART-30';
                        }
                        if (str_contains($textoMin, 'agres') || str_contains($textoMin, 'golpe')) {
                            $codigosArticulos[] = 'ART-18';
                        }
                        if (str_contains($textoMin, 'default') || str_contains($textoMin, 'abandono') || str_contains($textoMin, 'inasistencia')) {
                            $codigosArticulos[] = 'ART-35';
                        }

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

                        $textoInforme = mb_strtolower((string) $cedula->informe_arbitral, 'UTF-8');
                        if (str_contains($textoInforme, 'insult') || str_contains($textoInforme, 'ofens') || str_contains($textoInforme, 'reclamo')) {
                            $codigosArticulos[] = 'ART-15';
                        }
                        if (str_contains($textoInforme, 'porra') || str_contains($textoInforme, 'invas') || str_contains($textoInforme, 'riña') || str_contains($textoInforme, 'violenc')) {
                            $codigosArticulos[] = 'ART-30';
                        }
                        if (str_contains($textoInforme, 'agres') || str_contains($textoInforme, 'golpe')) {
                            $codigosArticulos[] = 'ART-18';
                        }

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

                    // Resolver e imputar artículos presuntamente infringidos del catálogo (REF-05)
                    $codigosUnicos = array_values(array_unique($codigosArticulos));
                    if ($codigosUnicos === []) {
                        $codigosUnicos = ['ART-12']; // Fallback por defecto
                    }

                    $articulosDb = CatalogoArticulo::whereIn('codigo', $codigosUnicos)
                        ->orderByDesc('castigo_base_partidos')
                        ->get();

                    // Si no existen en la BD, crearlos con datos por defecto según REF-05
                    if ($articulosDb->isEmpty()) {
                        $primerArticulo = CatalogoArticulo::firstOrCreate(
                            ['codigo' => 'ART-12'],
                            [
                                'descripcion' => 'Juego brusco grave o conducta violenta sin balón',
                                'castigo_base_partidos' => 2,
                            ]
                        );
                        $articulosDb = new Collection([$primerArticulo]);
                    }

                    // Imputar en la tabla pivote
                    foreach ($articulosDb as $art) {
                        $expediente->articulos()->syncWithoutDetaching([
                            $art->id => ['observaciones' => 'Artículo presuntamente infringido detectado en la cédula arbitral'],
                        ]);
                    }

                    // Asignar el artículo principal con mayor severidad en articulo_id
                    $articuloPrincipal = $articulosDb->first();
                    $expediente->update([
                        'articulo_id' => $articuloPrincipal?->id,
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
     * Asocia manualmente un artículo presunto adicional al expediente (Tribunal de Penas).
     */
    public function asociarArticuloPresunto(ExpedienteDisciplinario $expediente, int $articuloId, ?string $observaciones = null): void
    {
        $articulo = CatalogoArticulo::findOrFail($articuloId);

        $this->auditoria->conMotivo(
            "Imputación de presunta infracción al artículo {$articulo->codigo} en expediente {$expediente->folio}",
            function () use ($expediente, $articuloId, $observaciones): void {
                $expediente->articulos()->syncWithoutDetaching([
                    $articuloId => ['observaciones' => $observaciones ?? 'Imputado durante la revisión del expediente disciplinario'],
                ]);
            }
        );
    }

    /**
     * Remueve un artículo presunto del expediente.
     */
    public function desasociarArticuloPresunto(ExpedienteDisciplinario $expediente, int $articuloId): void
    {
        $articulo = CatalogoArticulo::findOrFail($articuloId);

        if ($expediente->articulos()->count() <= 1) {
            throw ValidationException::withMessages([
                'articulo' => 'El expediente debe conservar al menos un artículo reglamentario presuntamente infringido.',
            ]);
        }

        $this->auditoria->conMotivo(
            "Desestimación de presunta infracción al artículo {$articulo->codigo} en expediente {$expediente->folio}",
            function () use ($expediente, $articuloId): void {
                $expediente->articulos()->detach($articuloId);
                // Si era el artículo principal, reasignar al siguiente artículo disponible
                if ($expediente->articulo_id === $articuloId) {
                    $siguiente = $expediente->articulos()->first();
                    $expediente->update(['articulo_id' => $siguiente?->id]);
                }
            }
        );
    }
}
