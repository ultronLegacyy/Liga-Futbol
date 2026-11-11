<?php

namespace App\Services;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Consulta (HU-081) y edición (HU-083) de los permisos de cada rol (RF-12.1, IU-02).
// Todo se lee y se guarda en el motor de permisos (spatie/laravel-permission, HU-001): no hay permisos escritos en el código
class PermisosRolService
{
    // Supuesto de PA-18 (HU-083): el Administrador General no puede quitarse los permisos de administrar cuentas y permisos
    public const MODULOS_PROTEGIDOS_ADMIN = ['usuarios', 'roles'];

    public function __construct(
        private readonly AuditService $auditoria
    ) {
    }

    // Nombre visible de cada módulo (prefijo del permiso «modulo.accion»); si falta uno se usa el prefijo
    private const MODULOS = [
        'gobernanza' => 'Gobernanza',
        'ligas' => 'Ligas',
        'temporadas' => 'Temporadas',
        'convocatorias' => 'Convocatorias',
        'reglas_temporada' => 'Reglas de temporada',
        'categorias' => 'Categorías y grupos',
        'clubes' => 'Clubes',
        'tarjetones' => 'Tarjetón digital',
        'equipos' => 'Equipos',
        'jugadores' => 'Jugadores',
        'personas' => 'Personas',
        'campos' => 'Canchas',
        'usuarios' => 'Cuentas de usuario',
        'roles' => 'Roles y permisos',
        'auditoria' => 'Bitácora de auditoría',
        'arbitros' => 'Árbitros',
        'partidos' => 'Partidos',
        'cedulas' => 'Cédulas',
        'resultados' => 'Resultados',
        'disciplina' => 'Disciplina',
        'finanzas' => 'Finanzas',
    ];

    // Nombre visible de las acciones que no se leen bien solo cambiando «_» por espacio
    private const ACCIONES = [
        'pagos_validar' => 'Validar pagos',
        'asignar_permiso' => 'Asignar permisos',
    ];

    /**
     * CA1: los 13 roles con cuenta de la sección 2.3, en el orden del catálogo (sin «Público General», que no tiene cuenta).
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        $orden = array_flip(RoleAndPermissionSeeder::ROLES);

        return Role::query()
            ->whereIn('name', RoleAndPermissionSeeder::ROLES)
            ->withCount('permissions')
            ->get()
            ->sortBy(fn (Role $rol): int => $orden[$rol->name])
            ->values();
    }

    /**
     * Rol del catálogo; cualquier otro (o un id inexistente) responde 404.
     */
    public function rol(int $id): Role
    {
        return Role::query()
            ->whereIn('name', RoleAndPermissionSeeder::ROLES)
            ->findOrFail($id);
    }

    /**
     * CA2: módulos del motor de permisos con sus acciones, marcando cuáles tiene el rol.
     *
     * @return list<array{clave: string, nombre: string, acciones: list<array{permiso: string, nombre: string, permitida: bool}>, permitidas: int}>
     */
    public function modulos(Role $rol): array
    {
        $delRol = $rol->permissions()->pluck('name')->flip();
        $modulos = [];

        foreach (Permission::query()->where('guard_name', $rol->guard_name)->orderBy('id')->pluck('name') as $permiso) {
            [$clave, $accion] = array_pad(explode('.', $permiso, 2), 2, '');

            $modulos[$clave] ??= [
                'clave' => $clave,
                'nombre' => self::MODULOS[$clave] ?? Str::ucfirst(str_replace('_', ' ', $clave)),
                'acciones' => [],
                'permitidas' => 0,
            ];

            $permitida = $delRol->has($permiso);
            $modulos[$clave]['acciones'][] = [
                'permiso' => $permiso,
                'nombre' => self::ACCIONES[$accion] ?? Str::ucfirst(str_replace('_', ' ', $accion)),
                'permitida' => $permitida,
            ];
            $modulos[$clave]['permitidas'] += (int) $permitida;
        }

        return array_values($modulos);
    }

    /**
     * Todos los permisos del catálogo (los que se pueden asignar a un rol).
     *
     * @return list<string>
     */
    public function catalogo(): array
    {
        return Permission::query()->where('guard_name', 'web')->orderBy('id')->pluck('name')->values()->all();
    }

    /**
     * HU-083: guarda los permisos del rol y su registro en la bitácora en una sola transacción (CA1 y CA2).
     * El cambio aplica de inmediato a las cuentas con ese rol, porque Spatie lee los permisos del rol.
     * El rol queda marcado como editado para que la carga inicial no lo regrese a su valor original (CA4).
     *
     * @param  list<string>  $permisos  permisos que debe tener el rol al terminar
     * @return array{agregados: list<string>, quitados: list<string>}
     */
    public function actualizar(Role $rol, array $permisos, string $motivo): array
    {
        $cambios = $this->auditoria->conMotivo($motivo, function () use ($rol, $permisos): array {
            // Se bloquea el rol para que dos ediciones al mismo tiempo no se pisen
            Role::query()->whereKey($rol->getKey())->lockForUpdate()->first();

            $antes = $rol->permissions()->orderBy('name')->pluck('name')->values()->all();
            $despues = collect($permisos)->unique()->sort()->values()->all();
            $agregados = array_values(array_diff($despues, $antes));
            $quitados = array_values(array_diff($antes, $despues));

            $this->validarCambio($rol, $agregados, $quitados);

            $rol->syncPermissions($despues);
            $rol->forceFill(['permisos_editados_en' => now()])->save();

            // CA2: usuario, fecha y hora, IP, valor anterior, valor nuevo y motivo (RNF-33, HU-004)
            $this->auditoria->registrar(
                'permisos_rol_editados',
                $rol,
                ['permisos' => $antes],
                ['permisos' => $despues, 'agregados' => $agregados, 'quitados' => $quitados],
            );

            return ['agregados' => $agregados, 'quitados' => $quitados];
        });

        // Las cuentas con el rol ven el cambio en su siguiente petición
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $cambios;
    }

    /**
     * @param  list<string>  $agregados
     * @param  list<string>  $quitados
     */
    private function validarCambio(Role $rol, array $agregados, array $quitados): void
    {
        if ($agregados === [] && $quitados === []) {
            throw ValidationException::withMessages([
                'permisos' => 'No cambiaste ningún permiso: marca o desmarca al menos una acción.',
            ]);
        }

        if ($rol->name !== 'Administrador General') {
            return;
        }

        $protegidos = array_filter(
            $quitados,
            fn (string $permiso): bool => in_array(Str::before($permiso, '.'), self::MODULOS_PROTEGIDOS_ADMIN, true),
        );

        if ($protegidos !== []) {
            throw ValidationException::withMessages([
                'permisos' => 'El Administrador General no puede quitarse los permisos de administrar cuentas y permisos ('.implode(', ', $protegidos).').',
            ]);
        }
    }
}
