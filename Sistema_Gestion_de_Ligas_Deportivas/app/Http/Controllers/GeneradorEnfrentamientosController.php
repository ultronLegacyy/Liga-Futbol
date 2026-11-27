<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarHorariosRequest;
use App\Http\Requests\ConfirmarRolRequest;
use App\Http\Requests\GenerarRolRequest;
use App\Jobs\ProgramarCalendarioMasivoJob;
use App\Models\Cancha;
use App\Models\Grupo;
use App\Models\Jornada;
use App\Models\Temporada;
use App\Services\GeneradorEnfrentamientosService;
use App\Services\ProgramacionAutomaticaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Controlador para la generación de enfrentamientos (HU-032) y programación automática de horarios (HU-074, HU-033)
class GeneradorEnfrentamientosController extends Controller
{
    public function __construct(
        private readonly GeneradorEnfrentamientosService $generadorService,
        private readonly ProgramacionAutomaticaService $programacionAutomaticaService,
    ) {
    }

    /**
     * Vista de configuración de parámetros para generación del rol regular.
     */
    public function configurar(Grupo $grupo): View|RedirectResponse
    {
        Gate::authorize('create', [Jornada::class, $grupo]);

        try {
            $this->generadorService->validarGrupoParaGeneracion($grupo);
        } catch (ValidationException $e) {
            return redirect()->route('grupos.jornadas.index', $grupo)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        $grupo->load(['categoria.temporada', 'equipos']);
        $jornadasExistentesCount = $grupo->jornadas()->count();
        $canchasDisponibles = Cancha::query()
            ->where('estado', 'Disponible')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'capacidad_partidos_consecutivos']);
        $horariosDefecto = (array) config('programacion.horarios_defecto', ['09:00', '11:00', '13:00', '15:00']);

        return view('jornadas.generar-rol', compact(
            'grupo',
            'jornadasExistentesCount',
            'canchasDisponibles',
            'horariosDefecto'
        ));
    }

    /**
     * Previsualización de la propuesta de enfrentamientos y descansos calculados.
     */
    public function previsualizar(GenerarRolRequest $request, Grupo $grupo): View|JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [Jornada::class, $grupo]);

        $opciones = array_merge(
            $request->validated(),
            ['asignar_horarios' => $request->boolean('asignar_horarios')],
            $request->construirOpcionesHorarios()
        );

        try {
            $propuesta = $this->generadorService->generarPropuestaRol($grupo, $opciones);
        } catch (ValidationException $e) {
            return redirect()->route('grupos.generar-rol', $grupo)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($propuesta);
        }

        $grupo->load(['categoria.temporada', 'equipos']);
        $sobrescribir = (bool) $request->boolean('sobrescribir');
        $asignarHorarios = (bool) $opciones['asignar_horarios'];
        $horariosSeleccionados = $opciones['horarios'];
        $canchasSeleccionadas = array_map('intval', (array) ($opciones['cancha_ids'] ?? []));

        $jornadasPrevias = $grupo->jornadas()->with('partidos')->get();
        $partidosPreviosCount = $jornadasPrevias->sum(fn ($j) => $j->partidos->count());
        $partidosConArbitroCount = $jornadasPrevias->sum(fn ($j) => $j->partidos->whereNotNull('arbitro_id')->count());

        return view('jornadas.previsualizar-rol', compact(
            'grupo',
            'propuesta',
            'sobrescribir',
            'asignarHorarios',
            'horariosSeleccionados',
            'canchasSeleccionadas',
            'partidosPreviosCount',
            'partidosConArbitroCount'
        ));
    }

    /**
     * Confirmar y persistir transaccionalmente el rol regular generado.
     */
    public function confirmar(ConfirmarRolRequest $request, Grupo $grupo): RedirectResponse
    {
        Gate::authorize('create', [Jornada::class, $grupo]);

        /** @var string $payloadString */
        $payloadString = $request->validated('fixture_payload');
        /** @var array<string, mixed> $propuesta */
        $propuesta = json_decode($payloadString, true);

        $asignarHorarios = $request->boolean('asignar_horarios');
        $opcionesHorarios = $request->construirOpcionesHorarios();

        try {
            /** @var array{jornadas: array<int, array{numero_jornada: int, fecha_inicio: string, fecha_fin: string, equipo_descanso_id: int|null, partidos: array<int, array{equipo_local_id: int, equipo_visitante_id: int}>}>} $propuestaTipada */
            $propuestaTipada = $propuesta;
            $resultado = $this->generadorService->guardarRolGenerado(
                $grupo,
                $propuestaTipada,
                (bool) $request->boolean('sobrescribir'),
                $asignarHorarios,
                $opcionesHorarios
            );
        } catch (ValidationException $e) {
            return redirect()->route('grupos.generar-rol', $grupo)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        } catch (\DomainException $e) {
            return redirect()->route('grupos.generar-rol', $grupo)
                ->with('error', $e->getMessage());
        }

        $mensaje = "Rol regular generado exitosamente: {$resultado['jornadas_creadas']} jornadas y {$resultado['partidos_creados']} partidos programados.";
        $redirect = redirect()->route('grupos.jornadas.index', $grupo);

        if (isset($resultado['resumen_horarios']) && is_array($resultado['resumen_horarios'])) {
            $resumen = $resultado['resumen_horarios'];
            $mensaje .= " Horarios asignados automáticamente: {$resumen['asignados']} de {$resumen['total_partidos']}.";
            if (! empty($resumen['conflictos'])) {
                $redirect->with('programacion_conflictos', $resumen['conflictos']);
            }
        }

        return $redirect->with('success', $mensaje);
    }

    /**
     * Generación automática de partidos para una jornada existente específica.
     */
    public function generarJornada(Jornada $jornada): RedirectResponse
    {
        Gate::authorize('update', $jornada);

        try {
            $this->generadorService->aplicarEnfrentamientosAJornada($jornada);
        } catch (ValidationException $e) {
            return redirect()->route('jornadas.show', $jornada)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        return redirect()->route('jornadas.show', $jornada)
            ->with('success', "Enfrentamientos generados exitosamente para la Jornada {$jornada->numero_jornada}.");
    }

    /**
     * Ejecuta la asignación automática de cancha, fecha y hora para una jornada existente (HU-074 / HU-033).
     */
    public function asignarHorariosJornada(AsignarHorariosRequest $request, Jornada $jornada): RedirectResponse|JsonResponse
    {
        $opciones = $this->construirOpcionesHorarios($request);

        try {
            $resumen = $this->programacionAutomaticaService->asignarHorariosAJornada($jornada, $opciones);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->validator->errors()->first() ?: $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return redirect()->route('jornadas.show', $jornada)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($resumen);
        }

        $mensaje = "Asignación automática completada en Jornada {$jornada->numero_jornada}: {$resumen['asignados']} de {$resumen['total_partidos']} partidos con sede y horario.";
        $redirect = redirect()->route('jornadas.show', $jornada)->with('success', $mensaje);

        if (! empty($resumen['conflictos'])) {
            $redirect->with('programacion_conflictos', $resumen['conflictos']);
        }

        return $redirect;
    }

    /**
     * Ejecuta la asignación automática de cancha, fecha y hora sobre todas las jornadas en borrador de un grupo (HU-074).
     */
    public function asignarHorariosGrupo(AsignarHorariosRequest $request, Grupo $grupo): RedirectResponse|JsonResponse
    {
        $opciones = $this->construirOpcionesHorarios($request);

        try {
            $resumen = $this->programacionAutomaticaService->asignarHorariosAGrupo($grupo, $opciones);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->validator->errors()->first() ?: $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return redirect()->route('grupos.jornadas.index', $grupo)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($resumen);
        }

        $mensaje = "Programación automática del grupo '{$grupo->nombre}': {$resumen['asignados']} de {$resumen['total_partidos']} partidos asignados ({$resumen['jornadas_procesadas']} jornadas).";
        $redirect = redirect()->route('grupos.jornadas.index', $grupo)->with('success', $mensaje);

        if (! empty($resumen['conflictos'])) {
            $redirect->with('programacion_conflictos', $resumen['conflictos']);
        }

        return $redirect;
    }

    /**
     * Programa masivamente todas las categorías y grupos de una temporada (síncrono o en segundo plano / RNF-07).
     */
    public function asignarHorariosTemporada(AsignarHorariosRequest $request, Temporada $temporada): RedirectResponse|JsonResponse
    {
        $opciones = $this->construirOpcionesHorarios($request);
        if ($request->filled('numero_jornada')) {
            $opciones['numero_jornada'] = (int) $request->validated('numero_jornada');
        }

        if ($request->boolean('en_segundo_plano')) {
            $usuarioId = $request->user()?->id;
            Cache::put(
                "programacion_masiva_temporada_{$temporada->id}",
                [
                    'estado' => 'en_proceso',
                    'solicitado_por_id' => $usuarioId,
                    'solicitado_en' => now()->toIso8601String(),
                ],
                now()->addHours(6)
            );
            ProgramarCalendarioMasivoJob::dispatch($temporada->id, $opciones, $usuarioId);

            $mensaje = "Programación masiva en segundo plano iniciada para la temporada '{$temporada->nombre}'.";

            if ($request->wantsJson()) {
                return response()->json([
                    'enqueued' => true,
                    'temporada_id' => $temporada->id,
                    'message' => $mensaje,
                ], 202);
            }

            return redirect()->route('temporadas.show', $temporada)->with('success', $mensaje);
        }

        try {
            $resumen = $this->programacionAutomaticaService->asignarHorariosMasivoTemporada($temporada, $opciones);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->validator->errors()->first() ?: $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return redirect()->route('temporadas.show', $temporada)
                ->with('error', $e->validator->errors()->first() ?: $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json($resumen);
        }

        $mensaje = "Programación masiva completada en '{$temporada->nombre}': {$resumen['asignados']} de {$resumen['total_partidos']} partidos asignados ({$resumen['duracion_ms']} ms).";
        $redirect = redirect()->route('temporadas.show', $temporada)->with('success', $mensaje);

        if (! empty($resumen['conflictos'])) {
            $redirect->with('programacion_conflictos', $resumen['conflictos']);
        }

        return $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    private function construirOpcionesHorarios(AsignarHorariosRequest $request): array
    {
        return array_merge($request->construirOpcionesHorarios(), [
            'reemplazar_existentes' => $request->boolean('reemplazar_existentes'),
        ]);
    }
}
