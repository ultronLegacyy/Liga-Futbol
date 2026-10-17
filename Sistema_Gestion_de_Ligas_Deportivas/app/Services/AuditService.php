<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AuditService
{
    // Motivo de la operación en curso; vive solo durante la petición (binding scoped)
    private ?string $motivo = null;

    /**
     * Registra una operación sobre un modelo con usuario, fecha y hora, IP, valores y motivo (RF-12.2).
     *
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     */
    public function registrar(
        string $accion,
        Model $modelo,
        ?array $anteriores = null,
        ?array $nuevos = null,
        ?string $motivo = null,
    ): AuditoriaLog {
        return AuditoriaLog::create([
            'usuario_id' => auth()->id(),
            'accion' => $accion,
            'tabla_afectada' => $modelo->getTable(),
            'registro_id' => $modelo->getKey(),
            'valores_anteriores' => $this->sinOcultos($modelo, $anteriores),
            'valores_nuevos' => $this->sinOcultos($modelo, $nuevos),
            'motivo' => $motivo ?? $this->motivo,
            'ip' => $this->ip(),
        ]);
    }

    /**
     * Ejecuta la operación en una sola transacción con el motivo dado: el cambio y su auditoría
     * se guardan juntos o no se guarda nada (RNF-22).
     *
     * @template TResultado
     *
     * @param  Closure(): TResultado  $operacion
     * @return TResultado
     */
    public function conMotivo(string $motivo, Closure $operacion): mixed
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('El motivo de la operación es obligatorio y no puede estar vacío.');
        }

        // Se restaura el motivo anterior al terminar, lo que permite anidar operaciones
        $motivoAnterior = $this->motivo;
        $this->motivo = $motivo;

        try {
            return DB::transaction($operacion);
        } finally {
            $this->motivo = $motivoAnterior;
        }
    }

    /**
     * Quita los atributos ocultos del modelo (contraseñas, secretos de 2FA, tokens).
     *
     * @param  array<string, mixed>|null  $valores
     * @return array<string, mixed>|null
     */
    private function sinOcultos(Model $modelo, ?array $valores): ?array
    {
        return $valores === null ? null : Arr::except($valores, $modelo->getHidden());
    }

    private function ip(): ?string
    {
        // En consola (seeders, comandos, colas) no hay petición real; las pruebas sí simulan una
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        return request()->ip();
    }
}
