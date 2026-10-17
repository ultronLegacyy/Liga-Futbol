<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\PermissionRegistrar;

// Audita la asignación y el retiro de roles y permisos; Laravel lo descubre solo, no se registra a mano
class AuditarCambiosDePermisos
{
    public function __construct(
        private readonly AuditService $auditoria,
        private readonly PermissionRegistrar $permisos,
    ) {
    }

    public function handleRolAsignado(RoleAttachedEvent $evento): void
    {
        $this->auditoria->registrar('rol_asignado', $evento->model, null, [
            'roles' => $this->nombres($evento->rolesOrIds, $this->permisos->getRoleClass()),
        ]);
    }

    public function handleRolRetirado(RoleDetachedEvent $evento): void
    {
        $this->auditoria->registrar('rol_retirado', $evento->model, [
            'roles' => $this->nombres($evento->rolesOrIds, $this->permisos->getRoleClass()),
        ]);
    }

    public function handlePermisoAsignado(PermissionAttachedEvent $evento): void
    {
        $this->auditoria->registrar('permiso_asignado', $evento->model, null, [
            'permisos' => $this->nombres($evento->permissionsOrIds, $this->permisos->getPermissionClass()),
        ]);
    }

    public function handlePermisoRetirado(PermissionDetachedEvent $evento): void
    {
        $this->auditoria->registrar('permiso_retirado', $evento->model, [
            'permisos' => $this->nombres($evento->permissionsOrIds, $this->permisos->getPermissionClass()),
        ]);
    }

    /**
     * Convierte lo que envía Spatie (IDs, nombres, modelos o colecciones) en una lista de nombres.
     *
     * @return list<string>
     */
    private function nombres(mixed $elementos, string $clase): array
    {
        $lista = $elementos instanceof Collection ? $elementos->all() : Arr::wrap($elementos);
        $nombres = [];
        $ids = [];

        foreach ($lista as $elemento) {
            if ($elemento instanceof Model) {
                $nombres[] = (string) $elemento->getAttribute('name');
            } elseif (is_int($elemento)) {
                $ids[] = $elemento;
            } elseif (is_string($elemento)) {
                $nombres[] = $elemento;
            }
        }

        // Los IDs se traducen a nombres en una sola consulta
        if ($ids !== []) {
            array_push($nombres, ...$clase::query()->whereKey($ids)->pluck('name')->map(fn ($nombre) => (string) $nombre)->all());
        }

        return array_values(array_unique($nombres));
    }
}
