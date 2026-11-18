<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Historial de cambios de los datos de un jugador (HU-022, CA2, RF-04.5).
 *
 * No guarda nada propio: lee la bitácora inmutable de HU-004 (auditoria_logs), donde cada cambio
 * ya queda con usuario, fecha y hora, IP, valores anteriores y nuevos, y motivo.
 * Junta lo que cambió en las inscripciones de la persona, en sus datos personales y en sus documentos.
 * Decisión del 13/11: se juntan las 5 inscripciones más recientes de la persona, pero solo las que
 * quien consulta puede ver según JugadorPolicy::view (RNF-29): un Delegado no ve las de otro club.
 */
class HistorialCambiosJugadorService
{
    /** Cuántas inscripciones de la persona se juntan en el historial (decisión del 13/11). */
    public const MAXIMO_INSCRIPCIONES = 5;

    /** Nombres que se muestran en lugar de las acciones técnicas de la bitácora. */
    public const ACCIONES = [
        'crear' => 'Alta',
        'actualizar' => 'Cambio de datos',
        'eliminar' => 'Eliminación',
        'documento_jugador_registrado' => 'Documento agregado',
    ];

    /** Qué parte de los datos cambió, según la tabla de la bitácora. */
    public const SECCIONES = [
        'jugadores_equipos' => 'Inscripción',
        'personas' => 'Datos personales',
        'documentos_persona' => 'Documentos',
    ];

    /** Nombres de los campos que se muestran; los que no están aquí no salen en la vista. */
    public const CAMPOS = [
        'dorsal' => 'Dorsal',
        'estado_elegibilidad' => 'Estado',
        'motivo_baja' => 'Motivo de baja',
        'fecha_baja' => 'Fecha de baja',
        'equipo_id' => 'Equipo',
        'nombre' => 'Nombre',
        'apellidos' => 'Apellidos',
        'fecha_nacimiento' => 'Fecha de nacimiento',
        'tipo' => 'Tipo de documento',
    ];

    /**
     * Las 5 inscripciones más recientes de la persona que el usuario puede consultar, con su equipo y temporada.
     * La inscripción que se está consultando siempre entra, porque ya pasó la autorización.
     *
     * @return Collection<int, Jugador>
     */
    public function inscripciones(Jugador $jugador, User $usuario): Collection
    {
        return Jugador::query()
            ->where('persona_id', $jugador->persona_id)
            ->with(['equipo:id,nombre,club_id,temporada_id', 'equipo.temporada:id,nombre,fecha_inicio'])
            ->get()
            ->sortByDesc(fn (Jugador $inscripcion): array => [
                (string) $inscripcion->equipo?->temporada?->getAttribute('fecha_inicio'),
                (int) $inscripcion->getKey(),
            ])
            ->take(self::MAXIMO_INSCRIPCIONES)
            ->filter(fn (Jugador $inscripcion): bool => $inscripcion->is($jugador) || Gate::forUser($usuario)->allows('view', $inscripcion))
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Jugador>  $inscripciones
     * @return LengthAwarePaginator<int, AuditoriaLog>
     */
    public function de(Jugador $jugador, Collection $inscripciones): LengthAwarePaginator
    {
        $documentos = $jugador->persona->documentos()->pluck('id');

        return AuditoriaLog::query()
            ->with('usuario:id,name')
            ->where(function (Builder $consulta) use ($jugador, $documentos, $inscripciones): void {
                $consulta
                    ->where(fn (Builder $c) => $c->where('tabla_afectada', 'jugadores_equipos')->whereIn('registro_id', $inscripciones->keys()))
                    ->orWhere(fn (Builder $c) => $c->where('tabla_afectada', 'personas')->where('registro_id', $jugador->persona_id))
                    ->when($documentos->isNotEmpty(), fn (Builder $c) => $c->orWhere(
                        fn (Builder $d) => $d->where('tabla_afectada', 'documentos_persona')->whereIn('registro_id', $documentos)
                    ));
            })
            ->latest()
            ->latest('id')
            ->paginate(25);
    }

    /**
     * A qué equipo y temporada pertenece un registro de inscripción; los datos personales y documentos son de la persona.
     *
     * @param  Collection<int, Jugador>  $inscripciones
     */
    public function inscripcionDe(AuditoriaLog $registro, Collection $inscripciones): ?string
    {
        if ($registro->getAttribute('tabla_afectada') !== 'jugadores_equipos') {
            return null;
        }

        $inscripcion = $inscripciones->get((int) $registro->getAttribute('registro_id'));
        $equipo = $inscripcion?->equipo;

        return $equipo === null ? null : $equipo->nombre.' · '.$equipo->temporada->nombre;
    }

    /**
     * Campos visibles del registro, con su valor anterior y nuevo, para la vista.
     * Solo salen los campos con nombre en CAMPOS: ids internos, rutas y marcas de tiempo no se muestran.
     *
     * @return list<array{campo: string, anterior: string, nuevo: string}>
     */
    public function cambios(AuditoriaLog $registro): array
    {
        $anteriores = $this->valores($registro->getAttribute('valores_anteriores'));
        $nuevos = $this->valores($registro->getAttribute('valores_nuevos'));
        $cambios = [];

        foreach (self::CAMPOS as $campo => $nombre) {
            if (! array_key_exists($campo, $anteriores) && ! array_key_exists($campo, $nuevos)) {
                continue;
            }

            $cambios[] = [
                'campo' => $nombre,
                'anterior' => $this->texto($campo, $anteriores[$campo] ?? null),
                'nuevo' => $this->texto($campo, $nuevos[$campo] ?? null),
            ];
        }

        return $cambios;
    }

    /**
     * Valores de la bitácora como arreglo; una alta no tiene valores anteriores (null).
     *
     * @return array<string, mixed>
     */
    private function valores(mixed $valores): array
    {
        return is_array($valores) ? $valores : [];
    }

    /** Valor legible: el nombre del equipo en lugar de su ID y las fechas en d/m/Y. */
    private function texto(string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if ($campo === 'equipo_id' && is_numeric($valor)) {
            return (string) (Equipo::query()->whereKey((int) $valor)->value('nombre') ?? 'Equipo '.$valor);
        }

        if (in_array($campo, ['fecha_nacimiento', 'fecha_baja'], true) && is_string($valor)) {
            try {
                return Carbon::parse($valor)->format('d/m/Y');
            } catch (\Throwable) {
                return $valor;
            }
        }

        return is_scalar($valor) ? (string) $valor : (string) json_encode($valor, JSON_UNESCAPED_UNICODE);
    }
}
