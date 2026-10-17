<?php

namespace App\Models\Concerns;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

// Audita automáticamente altas, cambios y bajas del modelo dentro de la misma transacción (sin afterCommit)
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $modelo): void {
            app(AuditService::class)->registrar('crear', $modelo, null, $modelo->getAttributes());
        });

        static::updated(function (Model $modelo): void {
            // Solo lo que cambió, sin la marca de actualización
            $nuevos = Arr::except($modelo->getChanges(), array_filter([$modelo->getUpdatedAtColumn()]));

            if ($nuevos === []) {
                return;
            }

            $anteriores = array_intersect_key($modelo->getPrevious(), $nuevos);

            app(AuditService::class)->registrar('actualizar', $modelo, $anteriores, $nuevos);
        });

        static::deleted(function (Model $modelo): void {
            app(AuditService::class)->registrar('eliminar', $modelo, $modelo->getRawOriginal());
        });
    }
}
