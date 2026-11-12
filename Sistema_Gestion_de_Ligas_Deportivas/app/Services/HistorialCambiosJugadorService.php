<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use App\Models\Jugador;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Historial de cambios de los datos de un jugador (HU-022, CA2, RF-04.5).
 *
 * No guarda nada propio: lee la bitácora inmutable de HU-004 (auditoria_logs), donde cada cambio
 * ya queda con usuario, fecha y hora, IP, valores anteriores y nuevos, y motivo.
 * Junta lo que cambió en la inscripción, en los datos de la persona y en sus documentos.
 */
class HistorialCambiosJugadorService
{
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
     * @return LengthAwarePaginator<int, AuditoriaLog>
     */
    public function de(Jugador $jugador): LengthAwarePaginator
    {
        $documentos = $jugador->persona->documentos()->pluck('id');

        return AuditoriaLog::query()
            ->with('usuario:id,name')
            ->where(function (Builder $consulta) use ($jugador, $documentos): void {
                $consulta
                    ->where(fn (Builder $c) => $c->where('tabla_afectada', 'jugadores_equipos')->where('registro_id', $jugador->getKey()))
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
                'anterior' => $this->texto($anteriores[$campo] ?? null),
                'nuevo' => $this->texto($nuevos[$campo] ?? null),
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

    private function texto(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return is_scalar($valor) ? (string) $valor : (string) json_encode($valor, JSON_UNESCAPED_UNICODE);
    }
}
